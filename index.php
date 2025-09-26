<?php
// Let's start the session. Think of it as a way to save information about the user as they browse our website.
// Vamos iniciar a sessão. Pense nisso como uma forma de salvar informações do usuário enquanto ele navega no nosso site.
session_start();

// This is where we decide which "page" to show. If nothing is specified in the URL, we'll just go to the login page.
// É aqui que a gente decide qual "página" exibir. Se nada for definido na URL, a gente assume que é a página de login.
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// Let's grab the username from the session, if it exists.
// Vamos pegar o nome do usuário da sessão, se ele estiver logado.
$nome_usuario = isset($_SESSION['usuario_logado']) ? $_SESSION['usuario_logado'] : '';

// This part of the code handles form submissions and other actions.
// Essa parte do código processa os formulários e outras ações.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'login':
            $nome_usuario_input = $_POST['nome_usuario'];
            if (!empty($nome_usuario_input)) {
                // If the user entered a name, we'll save it to the session.
                // Se o usuário digitou um nome, a gente salva na sessão.
                $_SESSION['usuario_logado'] = $nome_usuario_input;
                // Then, we redirect them to the home page.
                // Depois, a gente redireciona ele para a página inicial.
                header('Location: index.php?page=home');
                exit;
            }
            break;

        case 'salvar_contratacao':
            // We'll clean up the data from the form to make sure it's safe.
            // Vamos limpar os dados do formulário para garantir que estão seguros.
            $nome = htmlspecialchars(trim($_POST['nome']));
            $artista = htmlspecialchars(trim($_POST['artista_selecionado']));
            $cache = filter_var($_POST['cache'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $data_evento = htmlspecialchars(trim($_POST['data_evento']));
            $endereco = htmlspecialchars(trim($_POST['endereco']));

            if (!empty($nome) && !empty($artista) && !empty($data_evento) && !empty($endereco)) {
                // We'll format the data into a single line.
                // A gente vai formatar os dados em uma única linha.
                $linha = "Nome: $nome | Artista: $artista | Cachê: " . ($cache ? "R$ " . number_format($cache, 2, ',', '.') : "Não Informado") . " | Data: $data_evento | Endereço: $endereco\n";
                $arquivo = 'contratacoes.txt';
                // And save it to our file.
                // E salvamos no nosso arquivo.
                if (file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX) !== false) {
                    header('Location: index.php?page=sucesso');
                    exit;
                }
            }
            break;
    }
}

// Handles the logout action.
// Cuida da ação de logout.
if ($page === 'logout') {
    // This will clear all session variables and destroy the session.
    // Isso vai limpar todas as variáveis de sessão e destruir a sessão.
    session_unset();
    session_destroy();
    // Then we send the user back to the login page.
    // Depois, a gente manda o usuário de volta para a página de login.
    header('Location: index.php');
    exit;
}

// Security check: if the user is not logged in and is trying to access any page other than 'login', we redirect them to the login page.
// Checagem de segurança: se o usuário não está logado e tenta acessar qualquer página que não seja 'login', a gente o redireciona.
if (!isset($_SESSION['usuario_logado']) && $page !== 'login') {
    header('Location: index.php?page=login');
    exit;
}

// Our functions to fetch data from the iTunes API.
// Nossas funções para buscar dados na API do iTunes.
function buscarArtistas($termo) {
    // This URL searches for artists on iTunes.
    // Essa URL busca artistas no iTunes.
    $url = 'https://itunes.apple.com/search?term=' . urlencode($termo) . '&media=music&entity=musicArtist&limit=25';
    // We try to get the content from the URL. The '@' hides any errors.
    // A gente tenta pegar o conteúdo da URL. O '@' esconde qualquer erro.
    $resposta_json = @file_get_contents($url);
    $artistas_encontrados = [];
    if ($resposta_json !== FALSE) {
        $dados = json_decode($resposta_json, true);
        if (isset($dados['results'])) {
            // We loop through the results and grab the artist's name and ID.
            // A gente passa por cada resultado e pega o nome e o ID do artista.
            foreach ($dados['results'] as $resultado) {
                $artistas_encontrados[] = [
                    'name' => $resultado['artistName'],
                    'id' => $resultado['artistId']
                ];
            }
        }
    }
    return $artistas_encontrados;
}

function buscarMusicas($artistId) {
    // This URL searches for songs by a specific artist ID.
    // Essa URL busca músicas de um artista específico pelo ID.
    $url = 'https://itunes.apple.com/lookup?id=' . urlencode($artistId) . '&entity=song&limit=25';
    $resposta_json = @file_get_contents($url);
    $musicas_encontradas = [];
    if ($resposta_json !== FALSE) {
        $dados = json_decode($resposta_json, true);
        if (isset($dados['results'])) {
            // We'll skip the first result because it's usually the artist itself, not a song.
            // A gente pula o primeiro resultado porque geralmente é o artista, não a música.
            for ($i = 1; $i < count($dados['results']); $i++) {
                if (isset($dados['results'][$i]['trackName'])) {
                    $musicas_encontradas[] = $dados['results'][$i]['trackName'];
                }
            }
        }
    }
    return $musicas_encontradas;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ES - Sistema de Contratação</title>
    <!-- We'll link to our cool new stylesheet here! -->
    <!-- Vamos linkar com o nosso novo e estiloso CSS aqui! -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <!-- This is where we decide which content to show based on the page variable. -->
        <!-- É aqui que a gente decide qual conteúdo mostrar com base na variável da página. -->
        <?php
        // Login Page
        // Tela de Login
        if ($page === 'login') {
            $mensagem_erro = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'login' && empty($_POST['nome_usuario'])) {
                $mensagem_erro = 'Por favor, insira um nome de usuário para continuar.';
            }
        ?>
            <div class="card">
                <h1>Login</h1>
                <p>Faça login para continuar.</p>
                <?php if (!empty($mensagem_erro)): ?>
                    <div class="mensagem-erro"><?php echo htmlspecialchars($mensagem_erro); ?></div>
                <?php endif; ?>
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="input-group">
                        <label for="nome_usuario">Nome de Usuário:</label>
                        <input type="text" id="nome_usuario" name="nome_usuario" required>
                    </div>
                    <button type="submit" class="btn-submit">Entrar</button>
                </form>
            </div>
        <?php
        // Home Page
        // Tela Principal
        } elseif ($page === 'home') {
        ?>
            <div class="card">
                <h1>Bem-vindo, <?php echo htmlspecialchars($nome_usuario); ?></h1>
                <p>Login realizado com sucesso!</p>
                <p>A partir daqui, você pode escolher o que fazer:</p>
                <div class="actions">
                    <a href="index.php?page=pesquisa" class="btn-primary">Pesquisar Artistas</a>
                    <a href="index.php?page=ver_contratacoes" class="btn-secondary">Ver Contratações</a>
                    <a href="index.php?page=logout" class="btn-link">Sair</a>
                </div>
            </div>
        <?php
        // Artist Search Page
        // Tela de Pesquisa de Artistas
        } elseif ($page === 'pesquisa') {
            $artistas = [];
            $termo_pesquisa = '';
            if (isset($_GET['termo']) && !empty(trim($_GET['termo']))) {
                $termo_pesquisa = trim($_GET['termo']);
                $artistas = buscarArtistas($termo_pesquisa);
            }
        ?>
            <div class="card search-card">
                <h1>Pesquisar Artistas</h1>
                <p>Encontre a banda ou cantor para o seu evento.</p>
                <form action="index.php" method="GET" class="search-form">
                    <input type="hidden" name="page" value="pesquisa">
                    <input type="text" name="termo" placeholder="Digite o nome do artista..." value="<?php echo htmlspecialchars($termo_pesquisa); ?>">
                    <button type="submit" class="btn-submit">Pesquisar</button>
                </form>
            </div>
            
            <div class="card lista-artistas">
                <h2>Resultados da Pesquisa</h2>
                <?php if (!empty($artistas)): ?>
                    <ul>
                        <?php foreach ($artistas as $artista): ?>
                            <li>
                                <span><?php echo htmlspecialchars($artista['name']); ?></span>
                                <div class="artist-actions">
                                    <a href="index.php?page=ver_musicas&artista=<?php echo urlencode($artista['name']); ?>&id=<?php echo urlencode($artista['id']); ?>" class="btn-secondary">Ver Músicas</a>
                                    <a href="index.php?page=contratacao&artista=<?php echo urlencode($artista['name']); ?>" class="btn-contratar">Contratar</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (isset($_GET['termo'])): ?>
                    <p class="mensagem-erro">Nenhum artista encontrado para "<?php echo htmlspecialchars($termo_pesquisa); ?>". Tente outro termo.</p>
                <?php else: ?>
                    <p>Use a barra de pesquisa acima para encontrar artistas.</p>
                <?php endif; ?>
            </div>
        <?php
        // View Songs Page
        // Tela de Ver Músicas
        } elseif ($page === 'ver_musicas') {
            $musicas = [];
            $artista_selecionado = 'Artista';
            $mensagem_erro = null;
            if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
                $id_artista = trim($_GET['id']);
                $artista_selecionado = isset($_GET['artista']) ? urldecode($_GET['artista']) : 'Artista';
                $musicas = buscarMusicas($id_artista);
                if (empty($musicas)) {
                    $mensagem_erro = 'Nenhuma música encontrada para este artista.';
                }
            } else {
                $mensagem_erro = 'ID do artista não fornecido.';
            }
        ?>
            <div class="card">
                <h1>Músicas de <?php echo htmlspecialchars($artista_selecionado); ?></h1>
                <?php if (isset($mensagem_erro)): ?>
                    <p class="mensagem-erro"><?php echo $mensagem_erro; ?></p>
                <?php elseif (!empty($musicas)): ?>
                    <p>Confira a discografia mais popular do artista:</p>
                    <ul>
                        <?php foreach ($musicas as $musica): ?>
                            <li><?php echo htmlspecialchars($musica); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="actions">
                    <a href="index.php?page=pesquisa" class="btn-primary">Voltar para a Pesquisa</a>
                </div>
            </div>
        <?php
        // Hire Page
        // Tela de Contratação
        } elseif ($page === 'contratacao') {
            $artista_selecionado = isset($_GET['artista']) ? urldecode($_GET['artista']) : '';
        ?>
            <div class="card">
                <h1>Formulário de Contratação</h1>
                <p>Preencha os detalhes para contratar <?php echo htmlspecialchars($artista_selecionado); ?>.</p>
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="salvar_contratacao">
                    <div class="input-group">
                        <label for="nome">Seu Nome*:</label>
                        <input type="text" id="nome" name="nome" required>
                    </div>
                    <div class="input-group">
                        <label for="artista_selecionado">Artista Selecionado*:</label>
                        <input type="text" id="artista_selecionado" name="artista_selecionado" value="<?php echo htmlspecialchars($artista_selecionado); ?>" readonly required>
                    </div>
                    <div class="input-group">
                        <label for="cache">Cachê (R$):</label>
                        <input type="number" id="cache" name="cache" min="0" step="any">
                    </div>
                    <div class="input-group">
                        <label for="data_evento">Data do Evento*:</label>
                        <input type="date" id="data_evento" name="data_evento" required>
                    </div>
                    <div class="input-group">
                        <label for="endereco">Endereço*:</label>
                        <textarea id="endereco" name="endereco" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Enviar Contratação</button>
                </form>
            </div>
        <?php
        // Success Page
        // Tela de Sucesso
        } elseif ($page === 'sucesso') {
        ?>
            <div class="card">
                <h1>Contratação enviada com sucesso!</h1>
                <p>Sua solicitação de contratação foi registrada.</p>
                <div class="actions">
                    <a href="index.php?page=home" class="btn-primary">Voltar para o Início</a>
                    <a href="index.php?page=ver_contratacoes" class="btn-secondary">Ver Contratações</a>
                </div>
            </div>
        <?php
        // View Hires Page
        // Tela de Ver Contratações
        } elseif ($page === 'ver_contratacoes') {
            $contratacoes = [];
            $arquivo = 'contratacoes.txt';
            // We check if our file exists and get its content.
            // A gente verifica se nosso arquivo existe e pega o conteúdo dele.
            if (file_exists($arquivo)) {
                $conteudo = file_get_contents($arquivo);
                if ($conteudo !== false) {
                    // We'll split the content by line to get each individual hire.
                    // A gente vai separar o conteúdo por linha para ter cada contratação individual.
                    $contratacoes = explode("\n", trim($conteudo));
                }
            }
        ?>
            <div class="card lista-contratacoes">
                <h1>Contratações Realizadas</h1>
                <?php if (!empty($contratacoes) && $contratacoes[0] !== ''): ?>
                    <ul>
                        <?php foreach ($contratacoes as $contratacao): ?>
                            <li><?php echo htmlspecialchars($contratacao); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Nenhuma contratação foi realizada ainda.</p>
                <?php endif; ?>
                <div class="actions">
                    <a href="index.php?page=home" class="btn-primary">Voltar</a>
                </div>
            </div>
        <?php
        }
        ?>
    </div>
</body>
</html>
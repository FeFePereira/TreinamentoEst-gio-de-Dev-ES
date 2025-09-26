<?php
// Start the session to save user information.
// Inicia a sessão para salvar informações do usuário.
session_start();

$page = isset($_GET['page']) ? $_GET['page'] : 'login';

$nome_usuario = isset($_SESSION['usuario_logado']) ? $_SESSION['usuario_logado'] : '';

// Process form submissions and actions when the request method is POST.
// Processa submissões de formulário e ações quando o método de requisição é POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'login':
            $nome_usuario_input = $_POST['nome_usuario'];
            if (!empty($nome_usuario_input)) {
                // Save the username to the session and redirect.
                // Salva o nome do usuário na sessão e redireciona.
                $_SESSION['usuario_logado'] = $nome_usuario_input;
                header('Location: index.php?page=home');
                exit;
            }
            break;

        case 'salvar_contratacao':
            // Sanitize and validate form data for hiring.
            // Limpa e valida os dados do formulário de contratação.
            $nome = htmlspecialchars(trim($_POST['nome']));
            $artista = htmlspecialchars(trim($_POST['artista_selecionado']));
            $cache = filter_var($_POST['cache'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $data_evento = htmlspecialchars(trim($_POST['data_evento']));
            $endereco = htmlspecialchars(trim($_POST['endereco']));

            if (!empty($nome) && !empty($artista) && !empty($data_evento) && !empty($endereco)) {
                $linha = "Nome: $nome | Artista: $artista | Cachê: " . ($cache ? "R$ " . number_format($cache, 2, ',', '.') : "Não Informado") . " | Data: $data_evento | Endereço: $endereco\n";
                $arquivo = 'contratacoes.txt';
                // Append the data to the file with an exclusive lock for safety.
                // Adiciona os dados ao arquivo com um bloqueio exclusivo por segurança.
                if (file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX) !== false) {
                    header('Location: index.php?page=sucesso');
                    exit;
                }
            }
            break;
    }
}

// Handle the logout action.
// Cuida da ação de logout.
if ($page === 'logout') {
    // Clear all session variables and destroy the session.
    // Limpa todas as variáveis de sessão e destrói a sessão.
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Security check: redirect to login if not logged in and not on the 'login' page.
// Checagem de segurança: redireciona para o login se não estiver logado e não for a página 'login'.
if (!isset($_SESSION['usuario_logado']) && $page !== 'login') {
    header('Location: index.php?page=login');
    exit;
}

// Function to fetch artists from the iTunes API based on a search term.
// Função para buscar artistas na API do iTunes com base em um termo de pesquisa.
function buscarArtistas($termo) {
    // Construct the API URL for artist search.
    // Constrói a URL da API para busca de artistas.
    $url = 'https://itunes.apple.com/search?term=' . urlencode($termo) . '&media=music&entity=musicArtist&limit=25';
    // Fetch the JSON response. The '@' suppresses connection errors.
    // Busca a resposta JSON. O '@' suprime erros de conexão.
    $resposta_json = @file_get_contents($url);
    $artistas_encontrados = [];
    if ($resposta_json !== FALSE) {
        $dados = json_decode($resposta_json, true);
        if (isset($dados['results'])) {
            // Loop through results to extract artist name and ID.
            // Percorre os resultados para extrair o nome e o ID do artista.
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

// Function to fetch songs by a specific artist ID from the iTunes API.
// Função para buscar músicas por um ID de artista específico na API do iTunes.
function buscarMusicas($artistId) {
    // Construct the API URL for looking up songs by artist ID.
    // Constrói a URL da API para pesquisa de músicas por ID de artista.
    $url = 'https://itunes.apple.com/lookup?id=' . urlencode($artistId) . '&entity=song&limit=25';
    $resposta_json = @file_get_contents($url);
    $musicas_encontradas = [];
    if ($resposta_json !== FALSE) {
        $dados = json_decode($resposta_json, true);
        if (isset($dados['results'])) {
            // Skip the first result (usually the artist profile) and list songs.
            // Pula o primeiro resultado (geralmente o perfil do artista) e lista as músicas.
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php
        // Logic for the 'login' page.
        // Lógica para a página de 'login'.
        if ($page === 'login') {
            $mensagem_erro = '';
            // Check for empty username on login attempt.
            // Verifica se o campo de nome de usuário está vazio na tentativa de login.
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
        // Logic for the 'home' page.
        // Lógica para a página 'home'.
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
        // Logic for the 'pesquisa' (search) page.
        // Lógica para a página de 'pesquisa'.
        } elseif ($page === 'pesquisa') {
            $artistas = [];
            $termo_pesquisa = '';
            // Execute search if a term is present in the URL.
            // Executa a busca se um termo estiver presente na URL.
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
        // Logic for the 'ver_musicas' (view songs) page.
        // Lógica para a página 'ver_musicas'.
        } elseif ($page === 'ver_musicas') {
            $musicas = [];
            $artista_selecionado = 'Artista';
            $mensagem_erro = null;
            // Fetch songs if the artist ID is valid.
            // Busca músicas se o ID do artista for válido.
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
        // Logic for the 'contratacao' (hire) page.
        // Lógica para a página de 'contratacao'.
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
        // Logic for the 'sucesso' (success) page.
        // Lógica para a página de 'sucesso'.
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
        // Logic for the 'ver_contratacoes' (view hires) page.
        // Lógica para a página 'ver_contratacoes'.
        } elseif ($page === 'ver_contratacoes') {
            $contratacoes = [];
            $arquivo = 'contratacoes.txt';
            // Check if the file exists and read its content.
            // Verifica se o arquivo existe e lê seu conteúdo.
            if (file_exists($arquivo)) {
                $conteudo = file_get_contents($arquivo);
                if ($conteudo !== false) {
                    // Split the content by newline to get individual hires.
                    // Divide o conteúdo por nova linha para obter contratações individuais.
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

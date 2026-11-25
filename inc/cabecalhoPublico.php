<?php
/* Cabeçalho das páginas públicas (antes do login). Defina $tituloPagina e $paginaAtiva antes de incluir. */
$paginaAtiva = $paginaAtiva ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tituloPagina ?? 'Acesso+'); ?> | Acesso+</title>
    <link rel="stylesheet" href="css/edit.css">
    <link rel="stylesheet" href="css/paginas.css">
</head>
<body>
    <nav>
        <div class="img-logo">
            <a href="index.html"><img src="img/logo.png" alt="Logo Acesso+ — voltar ao início" class="logo-img"></a>
        </div>
        <div class="links-nav">
            <a href="index.html">Início</a>
            <a href="planos.html">Planos</a>
            <a href="sobre.php" class="<?php echo $paginaAtiva === 'sobre' ? 'link-ativo' : ''; ?>">Sobre</a>
            <a href="contato.php" class="<?php echo $paginaAtiva === 'contato' ? 'link-ativo' : ''; ?>">Contato</a>
        </div>
        <button class="btn-nav" onclick="window.location.href='escolhaLogin.html'">Entrar</button>
    </nav>

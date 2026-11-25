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
    <link rel="stylesheet" href="css/site.css">
    <link rel="stylesheet" href="css/menu.css">
</head>
<body>
    <nav class="nav-site">
        <div class="img-logo">
            <a href="index.html"><img src="img/logo.png" alt="Acesso+ — página inicial" class="logo-img"></a>
        </div>
        <button type="button" class="btn-menu" aria-label="Abrir menu" aria-expanded="false" aria-controls="menu-principal"><svg class="icone-abrir" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg><svg class="icone-fechar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
        <div class="menu-principal" id="menu-principal">
            <div class="links-nav">
                <a href="index.html">Início</a>
                <a href="planos.html">Planos</a>
                <a href="index.html#beneficios">Benefícios</a>
                <a href="sobre.php"<?php echo $paginaAtiva === 'sobre' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Sobre</a>
                <a href="contato.php"<?php echo $paginaAtiva === 'contato' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Contato</a>
            </div>
            <a href="escolhaLogin.html" class="btn-nav">Entrar</a>
        </div>
    </nav>

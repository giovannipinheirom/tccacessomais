<?php
$paginaAtual = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="css/menu.css">
<nav class="nav-app" aria-label="Menu principal">
    <div class="img-logo">
        <a href="areaProfissional.php"><img src="img/logo.png" alt="Acesso+ — portal do profissional" class="logo-img"></a>
    </div>
    <button type="button" class="btn-menu" aria-label="Abrir menu" aria-expanded="false" aria-controls="menu-principal"><svg class="icone-abrir" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg><svg class="icone-fechar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    <div class="menu-principal" id="menu-principal">
        <div class="links-nav">
            <a href="areaProfissional.php"<?php echo $paginaAtual == 'areaProfissional.php' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Portal do Médico</a>
            <a href="minhaAgenda.php"<?php echo $paginaAtual == 'minhaAgenda.php' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Minha Agenda</a>
            <a href="gerenciarAgenda.php"<?php echo $paginaAtual == 'gerenciarAgenda.php' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Gerenciar Agenda</a>
            <a href="prontuario.php"<?php echo $paginaAtual == 'prontuario.php' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Prontuários</a>
        </div>

        <div class="nav-usuario">
            <span class="nav-nome">Dr(a). <?php echo htmlspecialchars($primeiroNome ?? 'Profissional'); ?></span>
            <a href="encerrarSessaoProfissional.php" class="btn-logout">Sair</a>
        </div>
    </div>
</nav>
<script src="js/menu.js" defer></script>

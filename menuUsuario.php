<?php 
// Descobre o nome do arquivo atual
$paginaAtual = basename($_SERVER['PHP_SELF']); 
?>

<!-- Importa o CSS específico do menu e acessibilidade -->
<link rel="stylesheet" href="css/menuUsuario.css">
<link rel="stylesheet" href="css/menu.css">

<nav class="nav-app" aria-label="Menu principal">
    <div class="img-logo">
        <a href="areaUsuario.php"><img src="img/logo.png" alt="Acesso+ — início da área do beneficiário" class="logo-img"></a>
    </div>
    <button type="button" class="btn-menu" aria-label="Abrir menu" aria-expanded="false" aria-controls="menu-principal"><svg class="icone-abrir" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg><svg class="icone-fechar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    <div class="menu-principal" id="menu-principal">
        <div class="links-nav">
            <a href="areaUsuario.php"<?php echo $paginaAtual == 'areaUsuario.php' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Início</a>
            <a href="meuPlano.php"<?php echo $paginaAtual == 'meuPlano.php' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Meu plano</a>
            <a href="agendamentos.php"<?php echo $paginaAtual == 'agendamentos.php' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Agendamentos</a>
            <a href="redeCredenciada.php"<?php echo $paginaAtual == 'redeCredenciada.php' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Rede credenciada</a>
            <a href="financeiro.php"<?php echo $paginaAtual == 'financeiro.php' ? ' class="link-ativo" aria-current="page"' : ''; ?>>Financeiro</a>
        </div>

        <!-- Botões de Acessibilidade -->
        <div class="acessibilidade-botoes" role="group" aria-label="Acessibilidade">
            <button type="button" onclick="ligarLeitorVoz()" title="Leitor de Tela" class="btn-acessibilidade">🔊 Ouvir</button>
            <button type="button" onclick="mudarFonte(1)" title="Aumentar Texto" aria-label="Aumentar texto" class="btn-acessibilidade">A+</button>
            <button type="button" onclick="mudarFonte(-1)" title="Diminuir Texto" aria-label="Diminuir texto" class="btn-acessibilidade">A-</button>
            <button type="button" onclick="alternarContraste()" title="Modo Escuro/Alto Contraste" aria-label="Alto contraste" class="btn-acessibilidade icone">◐</button>
        </div>

        <div class="nav-usuario">
            <span class="nav-nome"><?php echo htmlspecialchars($primeiroNome ?? 'Usuário'); ?></span>
            <a href="encerrarSessaoUser.php" class="btn-logout">Sair</a>
        </div>
    </div>
</nav>
<script src="js/menu.js" defer></script>

<!-- Script de Acessibilidade -->
<script>
    // --- 1. TAMANHO DA FONTE ---
    // Tenta recuperar o tamanho salvo, se não houver, usa 100
    let tamanhoAtual = parseInt(localStorage.getItem('acesso_tamanhoFonte')) || 100;
    
    // Aplica o tamanho assim que a página carrega
    document.body.style.zoom = (tamanhoAtual / 100);

    function mudarFonte(acao) {
        tamanhoAtual += (acao * 10);
        if (tamanhoAtual > 150) tamanhoAtual = 150;
        if (tamanhoAtual < 70) tamanhoAtual = 70;
        
        document.body.style.zoom = (tamanhoAtual / 100);
        localStorage.setItem('acesso_tamanhoFonte', tamanhoAtual); // Salva no navegador
    }

    // --- 2. ALTO CONTRASTE ---
    // Verifica se já estava ativado na outra página
    if (localStorage.getItem('acesso_altoContraste') === 'true') {
        document.body.classList.add('alto-contraste');
    }

    function alternarContraste() {
        document.body.classList.toggle('alto-contraste');
        
        // Salva a preferência
        if (document.body.classList.contains('alto-contraste')) {
            localStorage.setItem('acesso_altoContraste', 'true');
        } else {
            localStorage.setItem('acesso_altoContraste', 'false');
        }
    }

    // --- 3. LEITOR INTELIGENTE ---
    // Verifica se já estava ligado
    let leitorLigado = localStorage.getItem('acesso_leitorLigado') === 'true';

    function ligarLeitorVoz() {
        leitorLigado = !leitorLigado; // Inverte o status
        localStorage.setItem('acesso_leitorLigado', leitorLigado); // Salva

        if (leitorLigado) {
            alert("Leitor Inteligente ativado! Ele continuará ativo nas outras páginas.");
        } else {
            window.speechSynthesis.cancel();
            alert("Leitor Inteligente desativado.");
        }
    }

    document.addEventListener('mouseover', function(evento) {
        if (!leitorLigado) return;
        
        let elemento = evento.target;
        const tagsParaLer = ['H1', 'H2', 'H3', 'P', 'A', 'BUTTON', 'SPAN', 'IMG', 'LABEL', 'INPUT', 'SELECT', 'TEXTAREA'];
        
        if (tagsParaLer.includes(elemento.tagName)) {
            let textoParaLer = "";
            
            if (elemento.tagName === 'INPUT' || elemento.tagName === 'TEXTAREA') {
                textoParaLer = elemento.value || elemento.placeholder || elemento.name;
            } 
            else if (elemento.tagName === 'SELECT') {
                textoParaLer = elemento.options[elemento.selectedIndex].text;
            } 
            else {
                textoParaLer = elemento.innerText || elemento.alt; 
            }
            
            if (textoParaLer && textoParaLer.trim() !== '') {
                window.speechSynthesis.cancel(); 
                let robo = new SpeechSynthesisUtterance(textoParaLer.trim());
                robo.lang = 'pt-BR';
                window.speechSynthesis.speak(robo);
            }
        }
    });
</script>

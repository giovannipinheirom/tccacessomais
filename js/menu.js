// Abre e fecha o menu ☰ no celular (todas as páginas com <nav> responsivo)
document.querySelectorAll('.btn-menu').forEach(function (botao) {
    botao.addEventListener('click', function () {
        var nav = botao.closest('nav');
        var aberto = nav.classList.toggle('menu-aberto');
        botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        botao.setAttribute('aria-label', aberto ? 'Fechar menu' : 'Abrir menu');
    });
});

// Tecla Esc fecha o menu aberto
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('nav.menu-aberto').forEach(function (nav) {
        nav.classList.remove('menu-aberto');
        var b = nav.querySelector('.btn-menu');
        if (b) { b.setAttribute('aria-expanded', 'false'); b.setAttribute('aria-label', 'Abrir menu'); b.focus(); }
    });
});

let card = document.querySelector(".card");
let loginButton = document.querySelector(".loginButton");
let cadastroButton = document.querySelector(".cadastroButton");

loginButton.onclick = () => {
    card.classList.remove("cadastroActive");
    card.classList.add("loginActive");
}

cadastroButton.onclick = () => {
    card.classList.remove("loginActive");
    card.classList.add("cadastroActive");
}
// Confere as senhas do cadastro antes de enviar (as mesmas regras do cadastro de paciente)
const formCadastro = document.querySelector('.formCadastro form');
if (formCadastro) {
    formCadastro.addEventListener('submit', function (event) {
        const senha = document.getElementById('senha').value;
        const confSenha = document.getElementById('conf_senha').value;
        if (senha !== confSenha) {
            event.preventDefault();
            alert('As senhas não coincidem!');
            return;
        }
        const senhaInvalida = senha.length < 8 || !/[A-Z]/.test(senha) || !/[a-z]/.test(senha)
            || !/[0-9]/.test(senha) || !/[^A-Za-z0-9]/.test(senha);
        if (senhaInvalida) {
            event.preventDefault();
            alert('A senha não atende aos requisitos de segurança:\n- Mínimo de 8 caracteres\n- Pelo menos 1 letra maiúscula\n- Pelo menos 1 letra minúscula\n- Pelo menos 1 número\n- Pelo menos 1 símbolo (!@#$%^&*.)');
        }
    });
}

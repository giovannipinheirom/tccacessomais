/*
 * Validador de CPF (somente o cálculo dos dígitos verificadores, sem consulta à Receita).
 * Campos com o atributo data-validar-cpf são conferidos enquanto o usuário digita:
 * se o CPF for inválido, o formulário não é enviado e aparece a mensagem "CPF inválido".
 */
function cpfValido(valor) {
    const cpf = String(valor).replace(/\D/g, '');
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false; // 000.000.000-00, 111..., etc.

    // 1º dígito: pesos 10 a 2 nos 9 primeiros números; 2º dígito: pesos 11 a 2 nos 10 primeiros
    for (let tamanho = 9; tamanho <= 10; tamanho++) {
        let soma = 0;
        for (let i = 0; i < tamanho; i++) {
            soma += Number(cpf[i]) * (tamanho + 1 - i);
        }
        const digito = (soma * 10) % 11 % 10; // resto 10 vira 0
        if (digito !== Number(cpf[tamanho])) return false;
    }
    return true;
}

document.querySelectorAll('input[data-validar-cpf]').forEach(function (campo) {
    const aviso = document.createElement('span');
    aviso.className = 'aviso-cpf';
    aviso.setAttribute('aria-live', 'polite');
    // Se o campo está numa linha dupla (ex.: CPF + registro), a mensagem vai embaixo da linha
    const ancora = campo.parentElement.classList.contains('campos-duplos') ? campo.parentElement : campo;
    ancora.insertAdjacentElement('afterend', aviso);

    function conferir(mostrar) {
        const digitos = campo.value.replace(/\D/g, '');
        const ok = digitos === '' || cpfValido(digitos);
        campo.setCustomValidity(ok ? '' : 'CPF inválido. Confira os números digitados.');
        if (mostrar) {
            const completo = digitos.length === 11;
            campo.classList.toggle('cpf-invalido', !ok && (completo || mostrar === 'saiu'));
            campo.classList.toggle('cpf-valido', ok && completo);
            aviso.textContent = !ok && (completo || mostrar === 'saiu') ? 'CPF inválido. Confira os números digitados.' : '';
        }
    }

    campo.addEventListener('input', function () { conferir(true); });
    campo.addEventListener('blur', function () { if (campo.value) conferir('saiu'); });
    conferir(false);
});

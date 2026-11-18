<?php
include "conexao.php";
garantir_estrutura($con);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: escolhaLogin.html");
    exit();
}

$token = $_POST['token'] ?? '';
$senha = $_POST['senha'] ?? '';
$confirma_senha = $_POST['confirma_senha'] ?? '';

if ($senha !== $confirma_senha || strlen($senha) < 6) {
    echo "<script>alert('As senhas não coincidem ou têm menos de 6 caracteres. Tente novamente.'); window.history.back();</script>";
    exit();
}

// A nova senha é gravada criptografada (password_hash) e o token é apagado para o link não ser reutilizado.
// O token é conferido de novo aqui: só troca a senha de quem recebeu o link válido.
$contas = [
    ['Cadastro_Users',         'Senha_User',         'loginUser.html'],
    ['Cadastro_Profissionais', 'Senha_Profissional', 'loginProfissional.html'],
];
foreach ($contas as [$tabela, $colunaSenha, $paginaLogin]) {
    if ($token === '') break;
    $r = $con->query(
        "UPDATE $tabela SET $colunaSenha = ?, Token_Senha = NULL, Expiracao_Token = NULL
         WHERE Token_Senha = ? AND Expiracao_Token >= ?",
        [senha_hash($senha), $token, date("Y-m-d H:i:s")]
    );
    if ($r && $r->num_rows > 0) {
        echo "<script>alert('Senha atualizada com sucesso! Você já pode fazer login.'); window.location.href='$paginaLogin';</script>";
        exit();
    }
}

echo "<script>alert('Link inválido ou expirado. Solicite uma nova recuperação de senha.'); window.location.href='esqueciSenha.html';</script>";

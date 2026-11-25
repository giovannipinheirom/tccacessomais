<?php
/*
 * Troca de plano do paciente (aba "Meu plano").
 * Recebe o plano escolhido, confere o token do formulário e grava o novo plano.
 * A mensalidade do mês atual que ainda não foi paga passa a ter o valor do novo plano.
 */
session_start();

if (!isset($_SESSION['id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente') {
    header("location: loginUser.html?voltar=meuPlano.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !hash_equals($_SESSION['csrf_plano'] ?? '', (string)($_POST['csrf'] ?? ''))) {
    header("location: meuPlano.php?msg=plano_erro");
    exit();
}

include "conexao.php";
garantir_estrutura($con); // garante que a tabela Faturas existe

$idUsuario = (int)$_SESSION['id'];
$idPlano = (int)($_POST['id_plano'] ?? 0);

$plano = alterar_plano_usuario($con, $idUsuario, $idPlano);

header("location: meuPlano.php?msg=" . ($plano ? 'plano_alterado' : 'plano_erro'));
exit();

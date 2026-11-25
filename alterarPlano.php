<?php
/*
 * Troca de plano do paciente (aba "Meu plano").
 * Confere o token do formulário, grava o novo plano e leva o paciente direto ao Mercado Pago
 * para pagar a mensalidade (ou a diferença, se o mês já estava pago com um plano mais barato).
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
include "inc/pagamento.php";
garantir_estrutura($con);

$idUsuario = (int)$_SESSION['id'];
$troca = trocar_plano($con, $idUsuario, (int)($_POST['id_plano'] ?? 0));

if ($troca['situacao'] === 'pagar') {
    $checkout = checkout_mercado_pago($con, $troca['id_fatura'], $idUsuario);
    if (isset($checkout['url'])) {
        header("Location: " . $checkout['url']);
        exit();
    }
    alerta_e_volta("Plano alterado para " . $troca['plano']['Nome_Plano'] . ", mas o pagamento não abriu.\n" . $checkout['erro']
        . "\n\nVocê pode pagar depois pelo menu Financeiro.", 'financeiro.php');
}

header("location: meuPlano.php?msg=" . ($troca['situacao'] === 'sem_cobranca' ? 'plano_sem_cobranca' : 'plano_erro'));
exit();

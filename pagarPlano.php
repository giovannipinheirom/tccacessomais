<?php
/*
 * Leva o paciente direto ao Mercado Pago para pagar a fatura em aberto mais antiga
 * (usado logo após o cadastro, para começar o plano, e no botão "Pagar mensalidade" do Meu plano).
 */
session_start();

if (!isset($_SESSION['id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente') {
    header("location: loginUser.html?voltar=pagarPlano.php");
    exit();
}

include "conexao.php";
include "inc/pagamento.php";
garantir_estrutura($con);

$idUsuario = (int)$_SESSION['id'];
fatura_do_mes($con, $idUsuario); // garante que a mensalidade do mês existe
$fatura = fatura_em_aberto($con, $idUsuario);

if (!$fatura) {
    header("location: financeiro.php");
    exit();
}

$checkout = checkout_mercado_pago($con, (int)$fatura['Id_Fatura'], $idUsuario);
if (isset($checkout['url'])) {
    header("Location: " . $checkout['url']);
    exit();
}
alerta_e_volta($checkout['erro'], 'financeiro.php');

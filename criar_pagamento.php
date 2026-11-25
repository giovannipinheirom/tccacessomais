<?php
session_start();

// Verifica se é um paciente logado
if (!isset($_SESSION['id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente') {
    header("location: loginUser.html?voltar=financeiro.php");
    exit();
}

include "conexao.php";
include "inc/pagamento.php";
garantir_estrutura($con);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_fatura'])) {
    header("location: financeiro.php");
    exit();
}

$idUsuario = (int)$_SESSION['id'];
$checkout = checkout_mercado_pago($con, (int)$_POST['id_fatura'], $idUsuario);

if (isset($checkout['url'])) {
    header("Location: " . $checkout['url']);
    exit();
}
alerta_e_volta($checkout['erro'], 'financeiro.php');

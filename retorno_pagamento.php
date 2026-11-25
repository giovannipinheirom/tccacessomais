<?php
session_start();

// Verifica se é um paciente logado
if (!isset($_SESSION['id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente') {
    header("location: loginUser.html?voltar=financeiro.php");
    exit();
}

include "conexao.php";
garantir_estrutura($con);

$idUsuario = (int)$_SESSION['id'];
// O Mercado Pago devolve payment_id (ou collection_id) na URL de retorno
$paymentId = (string)($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');

// O id do pagamento é sempre numérico; qualquer outra coisa é descartada
if ($paymentId === '' || !ctype_digit($paymentId) || empty($mp_access_token)) {
    header("location: financeiro.php?msg=erro");
    exit();
}

// SEGURANÇA: Nunca confiamos apenas no ?status=approved da URL!
// Consultamos diretamente a API oficial do Mercado Pago pelo payment_id para confirmar a verdade.
$ch = curl_init("https://api.mercadopago.com/v1/payments/" . $paymentId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $mp_access_token,
    "Content-Type: application/json"
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300) {
    error_log('[Acesso+] Mercado Pago (consultar pagamento ' . $paymentId . ') HTTP ' . $httpCode);
    header("location: financeiro.php?msg=erro");
    exit();
}

$pagamento = json_decode((string)$resposta, true) ?: [];
$statusMP = $pagamento['status'] ?? '';
$idFatura = isset($pagamento['external_reference']) ? (int)$pagamento['external_reference'] : 0;

if ($statusMP === 'approved' && $idFatura > 0) {
    // Atualiza a fatura para Pago e salva o comprovante oficial do Mercado Pago
    $atualizada = $con->query(
        "UPDATE Faturas
         SET Status_Fatura = 'Pago', Id_Pagamento_MP = ?, Data_Pagamento = NOW()
         WHERE Id_Fatura = ? AND Id_User = ?",
        [$paymentId, $idFatura, $idUsuario]
    );

    // A fatura precisa ser deste paciente; senão o pagamento não vale para esta conta
    if (!$atualizada || $atualizada->num_rows == 0) {
        header("location: financeiro.php?msg=erro");
        exit();
    }

    // Ativa o plano do paciente (libera os agendamentos) se não restar nenhuma fatura em aberto
    $emAberto = $con->query(
        "SELECT Id_Fatura FROM Faturas WHERE Id_User = ? AND Status_Fatura <> 'Pago' LIMIT 1",
        [$idUsuario]
    );
    if ($emAberto && $emAberto->num_rows == 0) {
        $con->query("UPDATE Cadastro_Users SET Status_Pagamento = 'Ativo' WHERE Id_User = ?", [$idUsuario]);
    }

    header("location: financeiro.php?msg=aprovado");
    exit();
} elseif ($statusMP === 'pending' || $statusMP === 'in_process') {
    if ($idFatura > 0) {
        $con->query(
            "UPDATE Faturas SET Id_Pagamento_MP = ? WHERE Id_Fatura = ? AND Id_User = ?",
            [$paymentId, $idFatura, $idUsuario]
        );
    }
    header("location: financeiro.php?msg=pendente");
    exit();
} else {
    header("location: financeiro.php?msg=falha");
    exit();
}

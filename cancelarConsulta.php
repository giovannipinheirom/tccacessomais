<?php
/* Paciente cancela uma consulta futura (aba Agendamentos). O horário volta a ficar livre. */
session_start();
if (!isset($_SESSION['id']) || ($_SESSION['tipo_conta'] ?? '') !== 'paciente') {
    header("location: loginUser.html?voltar=agendamentos.php");
    exit();
}
include "conexao.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    header("location: agendamentos.php?msg=erro");
    exit();
}

$cancelada = $con->query(
    "UPDATE Consultas SET Status_Consulta = 'Cancelada'
     WHERE Id_Consulta = ? AND Id_User = ? AND Status_Consulta IN ('Pendente', 'Confirmado')
       AND (Data_Consulta > CURRENT_DATE OR (Data_Consulta = CURRENT_DATE AND Hora_Consulta > LOCALTIME))",
    [(int)($_POST['id_consulta'] ?? 0), (int)$_SESSION['id']]
);

header("location: agendamentos.php?msg=" . (($cancelada && $cancelada->num_rows > 0) ? 'cancelada' : 'nao_cancelada'));
exit();

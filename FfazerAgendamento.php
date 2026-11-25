<?php
/* Grava uma nova consulta do paciente (aba Agendamentos). */
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

$idUser = (int)$_SESSION['id'];
$idProfissional = (int)($_POST['id_profissional'] ?? 0);
$data = trim((string)($_POST['data'] ?? ''));
$hora = substr(trim((string)($_POST['hora'] ?? '')), 0, 5); // "14:00:00" -> "14:00"

// Bloqueio financeiro: só agenda quem está com a mensalidade em dia
$usuario = $con->query("SELECT Status_Pagamento FROM Cadastro_Users WHERE Id_User = ?", [$idUser]);
$usuario = $usuario ? $usuario->fetch_assoc() : null;
if (!$usuario || $usuario['Status_Pagamento'] !== 'Ativo') {
    header("location: financeiro.php?msg=agendamento_bloqueado");
    exit();
}

// Valida profissional, data (dia útil, a partir de hoje) e horário (da lista de atendimento e ainda não passado)
$profissional = $con->query("SELECT 1 FROM Cadastro_Profissionais WHERE Id_Profissional = ?", [$idProfissional]);
$valido = $profissional && $profissional->num_rows > 0
    && data_atendimento_valida($data)
    && in_array($hora, HORARIOS_EXPEDIENTE, true)
    && ($data . ' ' . $hora) > date('Y-m-d H:i');
if (!$valido) {
    header("location: agendamentos.php?msg=invalido");
    exit();
}

// Impede dois agendamentos (ou um bloqueio do médico) no mesmo horário
$conflito = $con->query(
    "SELECT 1 FROM Consultas WHERE Id_Profissional = ? AND Data_Consulta = ? AND Hora_Consulta = ?
     AND Status_Consulta NOT IN ('Cancelado', 'Cancelada')",
    [$idProfissional, $data, $hora . ':00']
);
if ($conflito && $conflito->num_rows > 0) {
    header("location: agendamentos.php?msg=indisponivel");
    exit();
}

$ok = $con->query(
    "INSERT INTO Consultas (Id_User, Id_Profissional, Data_Consulta, Hora_Consulta, Status_Consulta) VALUES (?, ?, ?, ?, 'Pendente')",
    [$idUser, $idProfissional, $data, $hora . ':00']
);
header("location: agendamentos.php?msg=" . ($ok ? 'agendada' : 'erro'));
exit();

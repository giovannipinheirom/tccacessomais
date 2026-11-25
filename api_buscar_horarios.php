<?php
// Horários livres de um profissional em um dia (usado pelo calendário da aba Agendamentos)
error_reporting(0); // erros não podem quebrar o JSON
header('Content-Type: application/json');

include 'conexao.php';

$idProfissional = (int)($_GET['medico_id'] ?? 0);
$dataConsulta = (string)($_GET['data'] ?? '');

if ($idProfissional <= 0 || !data_atendimento_valida($dataConsulta)) {
    echo json_encode([]);
    exit;
}

// Horários já ocupados (consultas ativas ou bloqueios do médico); canceladas liberam o horário
$resultado = $con->query(
    "SELECT Hora_Consulta FROM Consultas WHERE Id_Profissional = ? AND Data_Consulta = ?
     AND Status_Consulta NOT IN ('Cancelado', 'Cancelada')",
    [$idProfissional, $dataConsulta]
);
$ocupados = [];
while ($resultado && ($linha = $resultado->fetch_assoc())) $ocupados[] = substr($linha['Hora_Consulta'], 0, 5);

// Hoje só mostra os horários que ainda não passaram
$livres = array_values(array_filter(HORARIOS_EXPEDIENTE, function ($hora) use ($ocupados, $dataConsulta) {
    return !in_array($hora, $ocupados, true) && ($dataConsulta . ' ' . $hora) > date('Y-m-d H:i');
}));

echo json_encode($livres);

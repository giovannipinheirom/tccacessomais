<?php
    session_start();
    if(!isset($_SESSION['CPF']) || !isset($_SESSION['nome']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'paciente') {
        header("location: loginUser.html?voltar=agendamentos.php");
        exit();
    }
    include "conexao.php";

    $nomeCompleto = $_SESSION['nome'];
    $primeiroNome = explode(' ', trim($nomeCompleto))[0];
    $IdUsuario = (int)$_SESSION['id'];
    $csrf = csrf_token();

    // Médicos para o formulário
    $resultadoProfissionais = $con->query(
        "SELECT Id_Profissional, Nome_Profissional, Especialidade_Profissional FROM Cadastro_Profissionais ORDER BY Especialidade_Profissional, Nome_Profissional"
    );

    // Consultas do paciente: próximas primeiro (da mais perto para a mais longe), depois o histórico
    $resultadoAgendamentos = $con->query(
        "SELECT c.*, p.Nome_Profissional, p.Especialidade_Profissional
         FROM Consultas c
         INNER JOIN Cadastro_Profissionais p ON c.Id_Profissional = p.Id_Profissional
         WHERE c.Id_User = ?
         ORDER BY (c.Data_Consulta < CURRENT_DATE),
                  CASE WHEN c.Data_Consulta >= CURRENT_DATE THEN c.Data_Consulta END ASC,
                  c.Data_Consulta DESC, c.Hora_Consulta ASC",
        [$IdUsuario]
    );

    $mensagens = [
        'agendada'      => ['sucesso', 'Consulta agendada com sucesso! O profissional confirmará em breve.'],
        'cancelada'     => ['sucesso', 'Consulta cancelada. O horário foi liberado.'],
        'indisponivel'  => ['erro', 'Esse horário acabou de ser ocupado. Escolha outro horário.'],
        'invalido'      => ['erro', 'Escolha um profissional, um dia útil a partir de hoje e um dos horários da lista.'],
        'nao_cancelada' => ['erro', 'Não foi possível cancelar: a consulta já passou ou já foi finalizada.'],
        'erro'          => ['erro', 'Ocorreu um erro ao salvar. Tente novamente.'],
    ];
    $msg = $mensagens[$_GET['msg'] ?? ''] ?? null;
    $agora = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso+ | Agendamentos</title>
    <link rel="stylesheet" href="css/areaUsuario.css?v=3">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/pt.js"></script>
</head>
<body>
    <?php include 'menuUsuario.php'; ?>

    <header class="area-header">
        <div class="header-content">
            <span class="eyebrow">Agendamentos</span>
            <h1>Seus atendimentos</h1>
            <p>Acompanhe as consultas agendadas e solicite novos horários.</p>
        </div>
    </header>

    <main>
        <?php if ($msg): ?>
            <div class="alerta-plano <?php echo $msg[0]; ?>" role="<?php echo $msg[0] === 'erro' ? 'alert' : 'status'; ?>"><?php echo $msg[1]; ?></div>
        <?php endif; ?>

        <div class="painel">
            <section class="agendamentos">
                <h2>Seus agendamentos</h2>

                <?php if ($resultadoAgendamentos && $resultadoAgendamentos->num_rows > 0): ?>
                    <?php while ($consulta = $resultadoAgendamentos->fetch_assoc()):
                        $dataFormatada = date("d/m/Y", strtotime($consulta['Data_Consulta']));
                        $horaFormatada = date("H:i", strtotime($consulta['Hora_Consulta']));
                        $statusReal = $consulta['Status_Consulta'];
                        $statusClass = strtolower($statusReal);
                        $momento = $consulta['Data_Consulta'] . ' ' . $consulta['Hora_Consulta'];
                        $futura = $momento > $agora;

                        if ($statusClass === 'cancelado') $statusClass = 'cancelada';
                        // Pendente com data que já passou: o atendimento não foi registrado
                        if ($statusClass === 'pendente' && !$futura) {
                            $statusReal = "Não realizada";
                            $statusClass = "atrasada";
                        }
                        $podeCancelar = $futura && in_array(strtolower($consulta['Status_Consulta']), ['pendente', 'confirmado'], true);
                    ?>
                    <div class="agendamento-item">
                        <img src="img/medico.png" alt="" class="agendamento-icone">
                        <div class="agendamento-info">
                            <h3><?php echo htmlspecialchars($consulta['Especialidade_Profissional']); ?> — Dr(a). <?php echo htmlspecialchars($consulta['Nome_Profissional']); ?></h3>
                            <p><?php echo $dataFormatada; ?> às <?php echo $horaFormatada; ?></p>
                        </div>
                        <div class="agendamento-acoes">
                            <span class="status <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($statusReal); ?></span>
                            <?php if ($podeCancelar): ?>
                                <form action="cancelarConsulta.php" method="post"
                                      onsubmit="return confirm(<?php echo htmlspecialchars(json_encode('Cancelar a consulta de ' . $dataFormatada . ' às ' . $horaFormatada . '?', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>);">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="id_consulta" value="<?php echo (int)$consulta['Id_Consulta']; ?>">
                                    <button type="submit" class="btn-cancelar-consulta">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="lista-vazia">Você ainda não possui consultas agendadas. Use o formulário ao lado para marcar a primeira.</p>
                <?php endif; ?>
            </section>

            <section class="novo-agendamento">
                <h2>Agendar consulta</h2>

                <form action="FfazerAgendamento.php" method="POST">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <label for="id_profissional">Profissional e especialidade</label>
                    <select id="id_profissional" name="id_profissional" required>
                        <option value="" disabled selected hidden>Selecione um profissional</option>
                        <?php while ($resultadoProfissionais && ($prof = $resultadoProfissionais->fetch_assoc())): ?>
                            <option value="<?php echo (int)$prof['Id_Profissional']; ?>">Dr(a). <?php echo htmlspecialchars($prof['Nome_Profissional']); ?> — <?php echo htmlspecialchars($prof['Especialidade_Profissional']); ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label for="data">Data preferida</label>
                    <input type="text" id="data" name="data" placeholder="Selecione uma data" required autocomplete="off">

                    <label for="hora">Horário da consulta</label>
                    <select id="hora" name="hora" required disabled>
                        <option value="" disabled selected hidden>Selecione o médico e a data primeiro</option>
                    </select>

                    <button type="submit">Confirmar agendamento</button>
                    <p class="dica-agendamento">Atendimentos de segunda a sexta, das 8h às 18h. Consultas de 1 hora.</p>
                </form>
            </section>
        </div>
    </main>

    <footer class="rodape-painel">
        <p>© 2026 Acesso+ | Painel do Beneficiário</p>
    </footer>
    <script src="js/agendamento.js"></script>
</body>
</html>

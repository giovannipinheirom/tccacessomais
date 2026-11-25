<?php
    session_start();
    if(!isset($_SESSION['CPF']) || !isset($_SESSION['nome']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'profissional') {
        header("location: loginProfissional.html");
        exit();
    }

    $nomeCompleto = $_SESSION['nome']; 
    $primeiroNome = explode(' ', trim($nomeCompleto))[0];
    include "conexao.php";

    $IdProfissional = $_SESSION['id'] ?? 0;

    $csrf = csrf_token();

    // ----- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO -----
    // Os bloqueios são gravados como "consultas" sem paciente (Id_User = NULL) e status Bloqueado.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';
        $aviso = 'erro';

        if (!csrf_ok()) {
            $aviso = 'erro';
        } elseif ($acao == 'bloquear_horario') {
            $data = (string)($_POST['data_bloqueio'] ?? '');
            $hora = (string)($_POST['hora_bloqueio'] ?? '');
            if (!data_atendimento_valida($data) || !in_array($hora, HORARIOS_EXPEDIENTE, true)) {
                $aviso = 'invalido';
            } else {
                // Não bloqueia por cima de uma consulta já marcada (nem duplica um bloqueio)
                $ocupado = $con->query(
                    "SELECT 1 FROM Consultas WHERE Id_Profissional = ? AND Data_Consulta = ? AND Hora_Consulta = ?
                     AND Status_Consulta NOT IN ('Cancelado', 'Cancelada')",
                    [(int)$IdProfissional, $data, "$hora:00"]
                );
                if ($ocupado && $ocupado->num_rows > 0) {
                    $aviso = 'ocupado';
                } else {
                    $con->query(
                        "INSERT INTO Consultas (Id_User, Id_Profissional, Data_Consulta, Hora_Consulta, Status_Consulta, Observacoes_Medico)
                         VALUES (NULL, ?, ?, ?, 'Bloqueado', 'Bloqueio manual de agenda')",
                        [(int)$IdProfissional, $data, "$hora:00"]
                    );
                    $aviso = 'horario';
                }
            }

        } elseif ($acao == 'bloquear_dia') {
            $dataDia = (string)($_POST['data_dia'] ?? '');
            if (!data_atendimento_valida($dataDia)) {
                $aviso = 'invalido';
            } else {
                foreach (HORARIOS_EXPEDIENTE as $h) {
                    // Só bloqueia horários vazios (não cancela consultas de pacientes já marcados)
                    $resCheck = $con->query(
                        "SELECT 1 FROM Consultas WHERE Id_Profissional = ? AND Data_Consulta = ? AND Hora_Consulta = ?
                         AND Status_Consulta NOT IN ('Cancelado', 'Cancelada')",
                        [(int)$IdProfissional, $dataDia, "$h:00"]
                    );
                    if ($resCheck && $resCheck->num_rows == 0) {
                        $con->query(
                            "INSERT INTO Consultas (Id_User, Id_Profissional, Data_Consulta, Hora_Consulta, Status_Consulta, Observacoes_Medico)
                             VALUES (NULL, ?, ?, ?, 'Bloqueado', 'Férias / Dia inteiro')",
                            [(int)$IdProfissional, $dataDia, "$h:00"]
                        );
                    }
                }
                $aviso = 'dia';
            }

        } elseif ($acao == 'desbloquear') {
            $con->query(
                "DELETE FROM Consultas WHERE Id_Consulta = ? AND Id_Profissional = ? AND Status_Consulta = 'Bloqueado'",
                [(int)($_POST['id_bloqueio'] ?? 0), (int)$IdProfissional]
            );
            $aviso = 'liberado';
        }
        header("location: gerenciarAgenda.php?msg=$aviso");
        exit();
    }

    $mensagens = [
        'horario'  => ['sucesso', 'Horário bloqueado com sucesso.'],
        'dia'      => ['sucesso', 'Dia bloqueado. Consultas que os pacientes já tinham marcado foram mantidas.'],
        'liberado' => ['sucesso', 'Horário liberado para agendamento.'],
        'ocupado'  => ['erro', 'Esse horário já tem uma consulta marcada ou já está bloqueado.'],
        'invalido' => ['erro', 'Escolha um dia útil a partir de hoje e um dos horários da lista.'],
        'erro'     => ['erro', 'Não foi possível concluir. Atualize a página e tente novamente.'],
    ];
    $msg = $mensagens[$_GET['msg'] ?? ''] ?? null;

    // ----- BUSCA OS BLOQUEIOS ATUAIS PARA MOSTRAR NA TELA -----
    $queryBloqueios = "SELECT Id_Consulta, Data_Consulta, Hora_Consulta, Observacoes_Medico 
                       FROM Consultas 
                       WHERE Id_Profissional = ? AND Status_Consulta = 'Bloqueado' AND Data_Consulta >= CURRENT_DATE
                       ORDER BY Data_Consulta ASC, Hora_Consulta ASC";
    $resultBloqueios = $con->query($queryBloqueios, [(int)$IdProfissional]);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso+ | Gerenciar Agenda</title>
    <link rel="stylesheet" href="css/areaUsuario.css">
    <link rel="stylesheet" href="css/areaProfissional.css">
    <link rel="stylesheet" href="css/gerenciarAgenda.css">
    <link rel="stylesheet" href="css/profissional.css">
    <!-- Usando o Flatpickr para os calendários -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
</head>
<body>
    <?php include 'menuProfissional.php'; ?>

    <header class="area-header">
        <div class="header-content">
            <span class="eyebrow">Controle de Horários</span>
            <h1>Gerenciar Agenda</h1>
            <p>Bloqueie horários específicos ou dias inteiros (férias/recessos).</p>
        </div>
    </header>

    <?php if ($msg): ?>
        <div class="alerta-plano <?php echo $msg[0]; ?>" role="<?php echo $msg[0] === 'erro' ? 'alert' : 'status'; ?>" style="margin: 24px auto 0; width: calc(100% - 48px);"><?php echo $msg[1]; ?></div>
    <?php endif; ?>

    <main class="gerenciar-container">
        <!-- Lado Esquerdo: Formulários -->
        <div class="coluna-forms">
            
            <div class="box-bloqueio">
                <h3>Bloquear Horário Específico</h3>
                <form method="POST">
                    <input type="hidden" name="acao" value="bloquear_horario">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <div class="form-group">
                        <label for="data_bloqueio">Data</label>
                        <input type="text" class="calendario" id="data_bloqueio" name="data_bloqueio" required placeholder="Selecione o dia" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="hora_bloqueio">Horário</label>
                        <select id="hora_bloqueio" name="hora_bloqueio" required>
                            <option value="" disabled selected hidden>Selecione a hora</option>
                            <option value="08:00">08:00</option><option value="09:00">09:00</option>
                            <option value="10:00">10:00</option><option value="11:00">11:00</option>
                            <option value="13:00">13:00</option><option value="14:00">14:00</option>
                            <option value="15:00">15:00</option><option value="16:00">16:00</option><option value="17:00">17:00</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-bloquear">Confirmar Bloqueio</button>
                </form>
            </div>

            <div class="box-bloqueio">
                <h3>Bloquear Dia Inteiro (Férias)</h3>
                <form method="POST">
                    <input type="hidden" name="acao" value="bloquear_dia">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <div class="form-group">
                        <label for="data_dia">Data para bloquear</label>
                        <input type="text" class="calendario" id="data_dia" name="data_dia" required placeholder="Selecione o dia" autocomplete="off">
                    </div>
                    <p class="aviso-bloqueio">Ao bloquear um dia inteiro, todos os horários livres desse dia sumirão para os pacientes. Pacientes que já agendaram antes do bloqueio serão mantidos.</p>
                    <button type="submit" class="btn-bloquear btn-bloquear-dia">Bloquear Dia Completo</button>
                </form>
            </div>

        </div>

        <!-- Lado Direito: Lista de Bloqueios -->
        <div class="coluna-lista">
            <h3 class="titulo-lista">Horários Bloqueados</h3>
            
            <?php if($resultBloqueios && $resultBloqueios->num_rows > 0): ?>
                <div class="lista-bloqueios">
                    <?php while($b = $resultBloqueios->fetch_assoc()): ?>
                        <div class="item-bloqueio">
                            <div class="info-bloqueio">
                                <span class="data"><?php echo date("d/m/Y", strtotime($b['Data_Consulta'])); ?> às <?php echo date("H:i", strtotime($b['Hora_Consulta'])); ?></span>
                                <span class="motivo"><?php echo htmlspecialchars($b['Observacoes_Medico']); ?></span>
                            </div>
                            <form method="POST" class="form-remover">
                                <input type="hidden" name="acao" value="desbloquear">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="id_bloqueio" value="<?php echo (int)$b['Id_Consulta']; ?>">
                                <button type="submit" class="btn-remover">Liberar</button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="empty-bloqueios">Sua agenda está totalmente livre. Nenhum bloqueio ativo.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="rodape-painel">
        <p>© 2026 Acesso+ | Portal do Profissional</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/pt.js"></script>
    <script>
        // Ativa o calendário nos campos
        flatpickr(".calendario", {
            locale: "pt",
            dateFormat: "Y-m-d",
            minDate: "today",
            disable: [
                function(date) {
                    return (date.getDay() === 0 || date.getDay() === 6);
                }
            ]
        });
    </script>
</body>
</html>

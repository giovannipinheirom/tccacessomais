<?php
    session_start();
    if(!isset($_SESSION['CPF']) || !isset($_SESSION['nome']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'profissional')
    {
        header("location: loginProfissional.html");
        exit();
    }

    $nomeCompleto = $_SESSION['nome'];
    $primeiroNome = explode(' ', trim($nomeCompleto))[0];
    include "conexao.php";

    $IdProfissional = (int)($_SESSION['id'] ?? 0);
    $csrf = csrf_token();

    // Salva o prontuário e finaliza a consulta (só consultas deste profissional que ainda não foram finalizadas)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_consulta'])) {
        $idConsulta = (int)$_POST['id_consulta'];
        $observacoes = trim((string)($_POST['observacoes'] ?? ''));
        if (!csrf_ok() || mb_strlen($observacoes) < 5) {
            header("location: prontuario.php?id_consulta=$idConsulta&msg=invalido");
            exit();
        }
        $salvo = $con->query(
            "UPDATE Consultas SET Status_Consulta = 'Realizada', Observacoes_Medico = ?
             WHERE Id_Consulta = ? AND Id_Profissional = ? AND Id_User IS NOT NULL
               AND Status_Consulta NOT IN ('Realizada', 'Cancelada', 'Cancelado')",
            [mb_substr($observacoes, 0, 10000), $idConsulta, $IdProfissional]
        );
        header("location: prontuario.php?msg=" . (($salvo && $salvo->num_rows > 0) ? 'salvo' : 'erro'));
        exit();
    }

    function cpf_formatado(string $cpf): string
    {
        $d = preg_replace('/\D/', '', $cpf);
        return strlen($d) === 11 ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2) : $cpf;
    }

    function idade(?string $nascimento): string
    {
        if (!$nascimento) return '';
        return (new DateTime($nascimento))->diff(new DateTime('today'))->y . ' anos';
    }

    $msg = $_GET['msg'] ?? '';
    $consulta = null;
    if (isset($_GET['id_consulta'])) {
        $r = $con->query(
            "SELECT c.Id_Consulta, c.Data_Consulta, c.Hora_Consulta, c.Status_Consulta, c.Observacoes_Medico,
                    u.Nome_User, u.CPF_User, u.Dta_Nasc_User, u.Num_Tel_User, p.Nome_Plano
             FROM Consultas c
             INNER JOIN Cadastro_Users u ON c.Id_User = u.Id_User
             LEFT JOIN Planos p ON p.Id_Plano = u.Id_Plano
             WHERE c.Id_Profissional = ? AND c.Id_Consulta = ?",
            [$IdProfissional, (int)$_GET['id_consulta']]
        );
        $consulta = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : false;
    } else {
        // Consultas que ainda precisam de prontuário (da mais antiga para a mais nova)
        $pendentes = $con->query(
            "SELECT c.Id_Consulta, c.Data_Consulta, c.Hora_Consulta, u.Nome_User
             FROM Consultas c
             INNER JOIN Cadastro_Users u ON c.Id_User = u.Id_User
             WHERE c.Id_Profissional = ? AND c.Status_Consulta IN ('Pendente', 'Confirmado')
             ORDER BY c.Data_Consulta ASC, c.Hora_Consulta ASC",
            [$IdProfissional]
        );
        // Últimos prontuários finalizados
        $finalizados = $con->query(
            "SELECT c.Id_Consulta, c.Data_Consulta, c.Hora_Consulta, u.Nome_User
             FROM Consultas c
             INNER JOIN Cadastro_Users u ON c.Id_User = u.Id_User
             WHERE c.Id_Profissional = ? AND c.Status_Consulta = 'Realizada'
             ORDER BY c.Data_Consulta DESC, c.Hora_Consulta DESC LIMIT 6",
            [$IdProfissional]
        );
    }
    $agora = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso+ | Prontuários</title>
    <link rel="stylesheet" href="css/areaUsuario.css?v=3">
    <link rel="stylesheet" href="css/areaProfissional.css">
    <link rel="stylesheet" href="css/profissional.css">
</head>
<body>
    <?php include 'menuProfissional.php'; ?>

    <header class="area-header">
        <div class="header-content">
            <span class="eyebrow">Prontuários</span>
            <h1><?php echo $consulta ? 'Atendimento' : 'Prontuários eletrônicos'; ?></h1>
            <p><?php echo $consulta ? 'Registre as anotações da consulta e finalize o atendimento.' : 'Selecione uma consulta para registrar o atendimento ou rever um prontuário.'; ?></p>
        </div>
    </header>

    <main class="conteudo-prof">
        <?php if ($msg === 'salvo'): ?>
            <div class="alerta-plano sucesso" role="status">Prontuário salvo e consulta finalizada com sucesso!</div>
        <?php elseif ($msg === 'erro'): ?>
            <div class="alerta-plano erro" role="alert">Não foi possível salvar: a consulta não existe ou já foi finalizada.</div>
        <?php elseif ($msg === 'invalido'): ?>
            <div class="alerta-plano erro" role="alert">Escreva as anotações da consulta (pelo menos 5 caracteres) antes de finalizar.</div>
        <?php endif; ?>

        <?php if ($consulta === false): ?>
            <section class="caixa-prof">
                <h2>Consulta não encontrada</h2>
                <p class="texto-suave">Esta consulta não existe ou pertence a outro profissional.</p>
                <a href="prontuario.php" class="link-voltar-prof">&larr; Voltar para os prontuários</a>
            </section>

        <?php elseif ($consulta):
            $isRealizada = strtolower($consulta['Status_Consulta']) === 'realizada';
            $isCancelada = in_array(strtolower($consulta['Status_Consulta']), ['cancelada', 'cancelado'], true);
        ?>
            <a href="minhaAgenda.php" class="link-voltar-prof">&larr; Voltar para a agenda</a>
            <div class="prontuario-grid">
                <aside class="ficha-paciente">
                    <span class="ficha-rotulo">Paciente</span>
                    <h2><?php echo htmlspecialchars($consulta['Nome_User']); ?></h2>
                    <dl>
                        <div><dt>CPF</dt><dd><?php echo htmlspecialchars(cpf_formatado((string)$consulta['CPF_User'])); ?></dd></div>
                        <div><dt>Idade</dt><dd><?php echo htmlspecialchars(idade($consulta['Dta_Nasc_User'])); ?></dd></div>
                        <div><dt>Telefone</dt><dd><?php echo htmlspecialchars((string)$consulta['Num_Tel_User']); ?></dd></div>
                        <div><dt>Plano</dt><dd><?php echo htmlspecialchars($consulta['Nome_Plano'] ?? '—'); ?></dd></div>
                        <div><dt>Consulta</dt><dd><?php echo date("d/m/Y", strtotime($consulta['Data_Consulta'])); ?> às <?php echo date("H:i", strtotime($consulta['Hora_Consulta'])); ?></dd></div>
                    </dl>
                    <span class="status <?php echo $isRealizada ? 'realizada' : ($isCancelada ? 'cancelada' : 'pendente'); ?>"><?php echo htmlspecialchars($consulta['Status_Consulta']); ?></span>
                </aside>

                <section class="caixa-prof">
                    <?php if ($isRealizada): ?>
                        <h2>Anotações do atendimento</h2>
                        <div class="anotacoes-salvas"><?php echo nl2br(htmlspecialchars((string)$consulta['Observacoes_Medico'])); ?></div>
                    <?php elseif ($isCancelada): ?>
                        <h2>Consulta cancelada</h2>
                        <p class="texto-suave">O paciente cancelou esta consulta. Não há prontuário a registrar.</p>
                    <?php else: ?>
                        <h2>Registrar atendimento</h2>
                        <form method="POST" action="prontuario.php" class="form-prontuario">
                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="id_consulta" value="<?php echo (int)$consulta['Id_Consulta']; ?>">
                            <label for="observacoes">Anotações da consulta</label>
                            <textarea id="observacoes" name="observacoes" required minlength="5" maxlength="10000"
                                      placeholder="Queixa principal, avaliação, conduta, prescrições e orientações..."></textarea>
                            <p class="texto-suave">Ao salvar, a consulta é marcada como <strong>realizada</strong> e as anotações não podem mais ser alteradas.</p>
                            <button type="submit" class="btn-prof" onclick="return confirm('Salvar o prontuário e finalizar a consulta?');">Salvar e finalizar consulta</button>
                        </form>
                    <?php endif; ?>
                </section>
            </div>

        <?php else: ?>
            <section class="caixa-prof">
                <h2>Aguardando prontuário</h2>
                <?php if ($pendentes && $pendentes->num_rows > 0): ?>
                    <div class="lista-prof">
                        <?php while ($row = $pendentes->fetch_assoc()):
                            $atrasada = ($row['Data_Consulta'] . ' ' . $row['Hora_Consulta']) < $agora; ?>
                            <a href="prontuario.php?id_consulta=<?php echo (int)$row['Id_Consulta']; ?>" class="item-prof">
                                <span class="item-data"><strong><?php echo date("d/m", strtotime($row['Data_Consulta'])); ?></strong><?php echo date("H:i", strtotime($row['Hora_Consulta'])); ?></span>
                                <span class="item-nome"><?php echo htmlspecialchars($row['Nome_User']); ?></span>
                                <span class="status <?php echo $atrasada ? 'atrasada' : 'pendente'; ?>"><?php echo $atrasada ? 'Em atraso' : 'Agendada'; ?></span>
                                <span class="item-acao">Atender &rarr;</span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="lista-vazia">Nenhuma consulta aguardando prontuário.</p>
                <?php endif; ?>
            </section>

            <section class="caixa-prof">
                <h2>Finalizados recentemente</h2>
                <?php if ($finalizados && $finalizados->num_rows > 0): ?>
                    <div class="lista-prof">
                        <?php while ($row = $finalizados->fetch_assoc()): ?>
                            <a href="prontuario.php?id_consulta=<?php echo (int)$row['Id_Consulta']; ?>" class="item-prof">
                                <span class="item-data"><strong><?php echo date("d/m", strtotime($row['Data_Consulta'])); ?></strong><?php echo date("H:i", strtotime($row['Hora_Consulta'])); ?></span>
                                <span class="item-nome"><?php echo htmlspecialchars($row['Nome_User']); ?></span>
                                <span class="status realizada">Realizada</span>
                                <span class="item-acao">Ver &rarr;</span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="lista-vazia">Nenhum prontuário finalizado ainda.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <footer class="rodape-painel">
        <p>© 2026 Acesso+ | Portal do Profissional</p>
    </footer>
</body>
</html>

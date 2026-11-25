<?php
    session_start();
    // Verifica se está logado e se é realmente uma conta de profissional
    if(!isset($_SESSION['CPF']) || !isset($_SESSION['nome']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] != 'profissional')
    {
        unset($_SESSION['CPF'], $_SESSION['senha'], $_SESSION['nome'], $_SESSION['tipo_conta']);
        header("location: loginProfissional.html");
        exit();
    }

    $nomeCompleto = $_SESSION['nome'];
    $primeiroNome = explode(' ', trim($nomeCompleto))[0];
    include "conexao.php";

    $IdProfissional = (int)($_SESSION['id'] ?? 0);

    $resultado = $con->query("SELECT * FROM Cadastro_Profissionais WHERE Id_Profissional = ?", [$IdProfissional]);
    $dados = ($resultado && $resultado->num_rows > 0) ? $resultado->fetch_assoc() : [];
    $especialidade = $dados['Especialidade_Profissional'] ?? '—';
    $registro = ($dados['Registro_Profissional'] ?? '—') . ' / ' . ($dados['UF_Registro_Profissional'] ?? '');
    $cidade = $dados['Cidade_Profissional'] ?? '';
    $endereco = trim(($dados['Rua_Profissional'] ?? '') . ', ' . ($dados['Numero_Profissional'] ?? ''), ', ');

    // Números do painel
    $contar = function (string $where) use ($con, $IdProfissional) {
        $r = $con->query("SELECT COUNT(*) AS total FROM Consultas c WHERE c.Id_Profissional = ? AND c.Id_User IS NOT NULL AND $where", [$IdProfissional]);
        return $r ? (int)($r->fetch_assoc()['total'] ?? 0) : 0;
    };
    $ativas = "c.Status_Consulta IN ('Pendente', 'Confirmado')";
    $numeros = [
        'hoje'      => $contar("$ativas AND c.Data_Consulta = CURRENT_DATE"),
        'proximas'  => $contar("$ativas AND (c.Data_Consulta > CURRENT_DATE OR (c.Data_Consulta = CURRENT_DATE AND c.Hora_Consulta >= LOCALTIME))"),
        'atrasadas' => $contar("$ativas AND (c.Data_Consulta < CURRENT_DATE OR (c.Data_Consulta = CURRENT_DATE AND c.Hora_Consulta < LOCALTIME))"),
        'realizadas'=> $contar("c.Status_Consulta = 'Realizada'"),
    ];

    $resultAgenda = $con->query(
        "SELECT c.Id_Consulta, c.Hora_Consulta, c.Status_Consulta, u.Nome_User, u.Num_Tel_User
         FROM Consultas c
         INNER JOIN Cadastro_Users u ON c.Id_User = u.Id_User
         WHERE c.Id_Profissional = ? AND c.Data_Consulta = CURRENT_DATE
           AND c.Status_Consulta NOT IN ('Cancelada', 'Cancelado')
         ORDER BY c.Hora_Consulta ASC",
        [$IdProfissional]
    );
    $dataHoje = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso+ | Portal do Profissional</title>
    <link rel="stylesheet" href="css/areaUsuario.css?v=3">
    <link rel="stylesheet" href="css/areaProfissional.css">
    <link rel="stylesheet" href="css/profissional.css">
</head>
<body>
    <?php include 'menuProfissional.php'; ?>

    <header class="area-header">
        <div class="header-content">
            <span class="eyebrow">Portal do profissional</span>
            <h1>Olá, Dr(a). <?php echo htmlspecialchars($primeiroNome); ?></h1>
            <p>Acompanhe suas consultas e gerencie sua agenda com facilidade.</p>
        </div>
    </header>

    <main class="conteudo-prof">
        <section class="resumo-numeros" aria-label="Resumo da agenda">
            <div class="numero-prof"><span>Consultas hoje</span><strong><?php echo $numeros['hoje']; ?></strong></div>
            <a href="minhaAgenda.php" class="numero-prof"><span>Próximas</span><strong><?php echo $numeros['proximas']; ?></strong></a>
            <a href="prontuario.php" class="numero-prof<?php echo $numeros['atrasadas'] ? ' alerta' : ''; ?>"><span>Aguardando prontuário</span><strong><?php echo $numeros['atrasadas']; ?></strong></a>
            <a href="minhaAgenda.php" class="numero-prof"><span>Realizadas</span><strong><?php echo $numeros['realizadas']; ?></strong></a>
        </section>

        <div class="portal-grid">
            <aside class="perfil-prof">
                <h2>Seu perfil</h2>
                <dl>
                    <div><dt>Especialidade</dt><dd><?php echo htmlspecialchars($especialidade); ?></dd></div>
                    <div><dt>Registro</dt><dd><?php echo htmlspecialchars($registro); ?></dd></div>
                    <?php if ($cidade !== ''): ?><div><dt>Atuação</dt><dd><?php echo htmlspecialchars($cidade); ?></dd></div><?php endif; ?>
                    <?php if ($endereco !== ''): ?><div><dt>Endereço</dt><dd><?php echo htmlspecialchars($endereco); ?></dd></div><?php endif; ?>
                </dl>
            </aside>

            <section class="caixa-prof">
                <h2>Consultas de hoje <small class="texto-suave">(<?php echo $dataHoje; ?>)</small></h2>
                <?php if ($resultAgenda && $resultAgenda->num_rows > 0): ?>
                    <div class="lista-prof">
                        <?php while ($c = $resultAgenda->fetch_assoc()):
                            $realizada = strtolower($c['Status_Consulta']) === 'realizada'; ?>
                            <a href="prontuario.php?id_consulta=<?php echo (int)$c['Id_Consulta']; ?>" class="item-prof">
                                <span class="item-data"><strong><?php echo date("H:i", strtotime($c['Hora_Consulta'])); ?></strong>hoje</span>
                                <span class="item-nome"><?php echo htmlspecialchars($c['Nome_User']); ?><small>Tel.: <?php echo htmlspecialchars((string)$c['Num_Tel_User']); ?></small></span>
                                <span class="status <?php echo $realizada ? 'realizada' : 'pendente'; ?>"><?php echo $realizada ? 'Realizada' : 'Agendada'; ?></span>
                                <span class="item-acao"><?php echo $realizada ? 'Ver' : 'Atender'; ?> &rarr;</span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="lista-vazia">Você não tem consultas agendadas para hoje. Aproveite o dia!</p>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="rodape-painel">
        <p>© 2026 Acesso+ | Portal do Profissional</p>
    </footer>
</body>
</html>

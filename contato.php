<?php
include 'conexao.php';
garantir_estrutura($con);

$enviado = false; $erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $assunto = trim($_POST['assunto'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $assunto === '' || mb_strlen($mensagem) < 10) {
        $erro = 'Preencha nome, um e-mail válido, o assunto e uma mensagem com pelo menos 10 caracteres.';
    } elseif ($con->query("INSERT INTO Mensagens_Contato (Nome_Contato, Email_Contato, Assunto_Contato, Mensagem_Contato) VALUES (?, ?, ?, ?)",
        [mb_substr($nome, 0, 255), mb_substr($email, 0, 255), mb_substr($assunto, 0, 150), mb_substr($mensagem, 0, 5000)])) {
        $enviado = true;
    } else {
        $erro = 'Não foi possível enviar sua mensagem agora. Tente novamente em alguns minutos.';
    }
}
$tituloPagina = 'Fale Conosco'; $paginaAtiva = 'contato';
include 'inc/cabecalhoPublico.php';
?>
    <main class="pagina">
        <h1>Fale Conosco</h1>
        <p class="subtitulo">Tire dúvidas sobre planos, cadastro, rede credenciada ou acessibilidade. Respondemos pelo e-mail informado.</p>

        <?php if ($enviado): ?>
            <div class="aviso ok" role="status">Mensagem enviada com sucesso! Nossa equipe retornará em até 2 dias úteis.</div>
        <?php elseif ($erro): ?>
            <div class="aviso erro" role="alert"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>

        <form class="form-contato" method="post" action="contato.php">
            <div><label for="nome">Nome completo</label><input id="nome" name="nome" required maxlength="255" value="<?php echo htmlspecialchars($enviado ? '' : ($_POST['nome'] ?? '')); ?>"></div>
            <div><label for="email">E-mail</label><input id="email" name="email" type="email" required maxlength="255" value="<?php echo htmlspecialchars($enviado ? '' : ($_POST['email'] ?? '')); ?>"></div>
            <div><label for="assunto">Assunto</label>
                <select id="assunto" name="assunto" required>
                    <option value="">Selecione</option>
                    <?php foreach (['Planos e valores', 'Cadastro e acesso', 'Rede credenciada', 'Agendamento de consultas', 'Acessibilidade', 'Quero ser credenciado', 'Outro'] as $op): ?>
                        <option <?php echo (!$enviado && ($_POST['assunto'] ?? '') === $op) ? 'selected' : ''; ?>><?php echo $op; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label for="mensagem">Mensagem</label><textarea id="mensagem" name="mensagem" required minlength="10" maxlength="5000"><?php echo htmlspecialchars($enviado ? '' : ($_POST['mensagem'] ?? '')); ?></textarea></div>
            <button type="submit">Enviar mensagem</button>
        </form>

        <h2>Atendimento</h2>
        <p>Segunda a sexta-feira, das 8h às 18h. As mensagens enviadas fora desse horário são respondidas no próximo dia útil.</p>
    </main>
<?php include 'inc/rodapePublico.php'; ?>

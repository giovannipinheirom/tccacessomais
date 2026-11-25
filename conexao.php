<?php
/**
 * Conexão com o banco PostgreSQL do Supabase (usado pelo site E pelo app Android via api_app.php).
 *
 * As credenciais NÃO ficam no código: são lidas de variáveis de ambiente.
 *  - No Railway: aba "Variables" do serviço.
 *  - Localmente: crie um arquivo .env (veja .env.example) ou exporte as variáveis.
 *
 * Variáveis usadas:
 *   DATABASE_URL         -> string de conexão do Supabase (Connect > Session pooler), ex:
 *                           postgresql://postgres.xxxx:SENHA@aws-0-sa-east-1.pooler.supabase.com:5432/postgres
 *   GOOGLE_MAPS_API_KEY  -> chave do Google Maps (opcional, usada na Rede Credenciada)
 *   MP_ACCESS_TOKEN      -> Access Token do Mercado Pago (Checkout Pro, usado no Financeiro).
 *                           Use o token de TESTE (APP_USR-... da conta de teste) durante o TCC.
 *   RESEND_API_KEY       -> API Key do Resend (re_...), usada para enviar o e-mail de "Esqueci minha senha".
 *                           É a mesma conta Resend ligada ao Supabase (Authentication > Emails > SMTP).
 *   RESEND_FROM          -> remetente, ex: "Acesso+ <nao-responda@seudominio.com.br>" (domínio verificado no Resend)
 *   SITE_URL             -> (opcional) endereço público do site, ex: https://xxxx.up.railway.app
 *                           usado para montar o link do e-mail de recuperação de senha.
 */

date_default_timezone_set('America/Sao_Paulo');

// Carrega um arquivo .env local, se existir (apenas para testes no computador)
if (is_readable(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linhaEnv) {
        $linhaEnv = trim($linhaEnv);
        if ($linhaEnv === '' || $linhaEnv[0] === '#' || strpos($linhaEnv, '=') === false) continue;
        [$chave, $valor] = array_map('trim', explode('=', $linhaEnv, 2));
        if (getenv($chave) === false) putenv("$chave=" . trim($valor, "\"'"));
    }
}

$google_maps_api_key = getenv('GOOGLE_MAPS_API_KEY') ?: '';
$mp_access_token = getenv('MP_ACCESS_TOKEN') ?: '';
$resend_api_key = getenv('RESEND_API_KEY') ?: '';
// Remetente dos e-mails. Precisa ser de um domínio verificado no Resend.
// Sem domínio verificado, o Resend só aceita "onboarding@resend.dev" e só entrega para o e-mail dono da conta Resend.
$resend_from = getenv('RESEND_FROM') ?: 'Acesso+ <onboarding@resend.dev>';

/**
 * O PostgreSQL devolve nomes de colunas em minúsculas (id_user), mas o código do site
 * usa os nomes originais do MySQL (Id_User). Este mapa restaura as maiúsculas/minúsculas.
 */
const COLUNAS_DO_BANCO = [
    'Id_Plano', 'Nome_Plano', 'Preco',
    'Id_User', 'Nome_User', 'Dta_Nasc_User', 'Genero_User', 'CPF_User', 'Est_Civil_User', 'Num_Tel_User',
    'CEP_User', 'Numero_User', 'Rua_User', 'Bairro_User', 'Cidade_User', 'Latitude_User', 'Longitude_User',
    'Email_User', 'Senha_User', 'Status_Pagamento', 'Token_Senha', 'Expiracao_Token',
    'Id_Profissional', 'Nome_Profissional', 'Email_Profissional', 'CPF_Profissional', 'Registro_Profissional',
    'UF_Registro_Profissional', 'CEP_Profissional', 'Numero_Profissional', 'Rua_Profissional',
    'Bairro_Profissional', 'Cidade_Profissional', 'Latitude_Profissional', 'Longitude_Profissional',
    'Especialidade_Profissional', 'Dta_Nasc_Profissional', 'Genero_Profissional', 'Num_Tel_Profissional',
    'Senha_Profissional',
    'Id_Consulta', 'Data_Consulta', 'Hora_Consulta', 'Status_Consulta', 'Observacoes_Medico', 'Data_Criacao_Consulta',
    'Id_Mensagem', 'Nome_Contato', 'Email_Contato', 'Assunto_Contato', 'Mensagem_Contato', 'Data_Envio', 'Lida',
    'Id_Fatura', 'Mes_Referencia', 'Valor', 'Data_Vencimento', 'Status_Fatura', 'Id_Pagamento_MP', 'Data_Pagamento',
    'Descricao',
];

/** Gera o hash seguro (bcrypt) de uma senha antes de gravar no banco. */
function senha_hash(string $senha): string
{
    return password_hash($senha, PASSWORD_DEFAULT);
}

/**
 * Confere a senha digitada com a salva no banco.
 * Aceita também senhas antigas gravadas sem criptografia (cadastros feitos antes desta versão).
 */
function senha_confere(string $digitada, ?string $salva): bool
{
    if ($salva === null || $salva === '') return false;
    if (password_get_info($salva)['algo'] !== null && password_get_info($salva)['algo'] !== 0) {
        return password_verify($digitada, $salva);
    }
    return hash_equals($salva, $digitada);
}

/** Indica se a senha salva ainda está sem criptografia (para atualizá-la no próximo login). */
function senha_precisa_hash(?string $salva): bool
{
    return $salva !== null && $salva !== '' && password_needs_rehash($salva, PASSWORD_DEFAULT);
}

/**
 * Cria no banco as estruturas adicionadas depois da primeira versão (pode rodar várias vezes).
 * Assim o site se atualiza sozinho, sem precisar rodar SQL manualmente no Supabase.
 */
function garantir_estrutura(Banco $con): void
{
    static $feito = false;
    if ($feito) return;
    $feito = true;
    $con->query("ALTER TABLE Cadastro_Profissionais ADD COLUMN IF NOT EXISTS Token_Senha VARCHAR(100) NULL");
    $con->query("ALTER TABLE Cadastro_Profissionais ADD COLUMN IF NOT EXISTS Expiracao_Token TIMESTAMP NULL");
    $con->query("CREATE TABLE IF NOT EXISTS Mensagens_Contato (
        Id_Mensagem SERIAL PRIMARY KEY,
        Nome_Contato VARCHAR(255) NOT NULL,
        Email_Contato VARCHAR(255) NOT NULL,
        Assunto_Contato VARCHAR(150) NOT NULL,
        Mensagem_Contato TEXT NOT NULL,
        Data_Envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        Lida BOOLEAN DEFAULT FALSE
    )");
    $con->query("ALTER TABLE Mensagens_Contato ENABLE ROW LEVEL SECURITY");
    // Mensalidades pagas pelo Mercado Pago (área Financeiro)
    $con->query("CREATE TABLE IF NOT EXISTS Faturas (
        Id_Fatura SERIAL PRIMARY KEY,
        Id_User INT NOT NULL REFERENCES Cadastro_Users(Id_User) ON DELETE CASCADE,
        Mes_Referencia VARCHAR(7) NOT NULL,
        Valor NUMERIC(10,2) NOT NULL,
        Data_Vencimento DATE NOT NULL,
        Status_Fatura VARCHAR(50) DEFAULT 'Pendente',
        Id_Pagamento_MP VARCHAR(100) NULL,
        Data_Pagamento TIMESTAMP NULL,
        Descricao VARCHAR(150) NULL
    )");
    $con->query("ALTER TABLE Faturas ENABLE ROW LEVEL SECURITY");

    // Troca de plano: além da mensalidade (Descricao vazia), o mês pode ter uma fatura com a diferença
    // entre os planos. A regra "uma fatura por mês" passa a valer só para a mensalidade.
    $temDescricao = $con->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = current_schema() AND table_name = 'faturas' AND column_name = 'descricao'"
    );
    if ($temDescricao && $temDescricao->num_rows === 0) {
        $con->query("ALTER TABLE Faturas ADD COLUMN IF NOT EXISTS Descricao VARCHAR(150) NULL");
    }
    $con->query("DO $$ DECLARE c text; BEGIN
        FOR c IN SELECT conname FROM pg_constraint WHERE conrelid = 'faturas'::regclass AND contype = 'u' LOOP
            EXECUTE 'ALTER TABLE faturas DROP CONSTRAINT ' || quote_ident(c);
        END LOOP;
    END $$");
    $con->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_faturas_mensalidade ON Faturas (Id_User, Mes_Referencia) WHERE Descricao IS NULL");
}

/** Horários de atendimento da rede (consultas de 1 hora, pausa para o almoço às 12h). */
const HORARIOS_EXPEDIENTE = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

/**
 * Validador de CPF: confere só o cálculo dos dois dígitos verificadores (não consulta a Receita).
 * Aceita com ou sem pontuação. Rejeita sequências repetidas como 111.111.111-11.
 */
function cpf_valido(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;

    // 1º dígito: pesos 10 a 2 nos 9 primeiros números; 2º dígito: pesos 11 a 2 nos 10 primeiros
    for ($tamanho = 9; $tamanho <= 10; $tamanho++) {
        $soma = 0;
        for ($i = 0; $i < $tamanho; $i++) {
            $soma += (int)$cpf[$i] * ($tamanho + 1 - $i);
        }
        $digito = ($soma * 10) % 11 % 10; // resto 10 vira 0
        if ($digito !== (int)$cpf[$tamanho]) return false;
    }
    return true;
}

/** Regras de senha do cadastro e da recuperação: retorna a lista do que falta (vazia = senha válida). */
function problemas_senha(string $senha): array
{
    $faltando = [];
    if (mb_strlen($senha) < 8) $faltando[] = 'ter pelo menos 8 caracteres';
    if (!preg_match('/[A-Z]/', $senha)) $faltando[] = 'ter uma letra maiúscula';
    if (!preg_match('/[a-z]/', $senha)) $faltando[] = 'ter uma letra minúscula';
    if (!preg_match('/[0-9]/', $senha)) $faltando[] = 'ter um número';
    if (!preg_match('/[^A-Za-z0-9]/', $senha)) $faltando[] = 'ter um símbolo (ex.: ! @ # $ . *)';
    return $faltando;
}

/** Confere se a data (AAAA-MM-DD) existe, é dia útil e não está no passado. */
function data_atendimento_valida(string $data): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $data);
    if (!$d || $d->format('Y-m-d') !== $data) return false;
    if ((int)$d->format('N') >= 6) return false; // sábado ou domingo
    return $data >= date('Y-m-d');
}

/** Token contra envio de formulários por outros sites (CSRF). A sessão precisa estar iniciada. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''));
}

/** Endereço público do site (SITE_URL ou o endereço que o navegador usou). */
function url_base_site(): string
{
    $siteUrl = getenv('SITE_URL') ?: '';
    if ($siteUrl !== '') return rtrim($siteUrl, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\');
}

/**
 * Envia um e-mail pela API do Resend (https://resend.com/docs/api-reference/emails/send-email).
 * Usa HTTPS (porta 443), que funciona no Railway; o SMTP (portas 25/465/587) costuma ser bloqueado lá.
 * Retorna true se o Resend aceitou o envio.
 */
function enviar_email(string $para, string $assunto, string $html, string $texto = ''): bool
{
    global $resend_api_key, $resend_from;
    if ($resend_api_key === '') return false;

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $resend_api_key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(array_filter([
            'from' => $resend_from,
            'to' => [$para],
            'subject' => $assunto,
            'html' => $html,
            'text' => $texto,
        ])),
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) return true;
    $dados = json_decode((string)$resposta, true) ?: [];
    error_log('[Acesso+] Resend HTTP ' . $httpCode . ': ' . ($dados['message'] ?? $erroCurl ?: 'erro desconhecido'));
    return false;
}

/** Resultado de uma consulta, com a mesma "cara" do mysqli (num_rows e fetch_assoc). */
class Resultado
{
    public int $num_rows;
    private array $linhas;
    private int $posicao = 0;

    public function __construct(array $linhas)
    {
        static $mapa = null;
        if ($mapa === null) {
            $mapa = [];
            foreach (COLUNAS_DO_BANCO as $coluna) $mapa[strtolower($coluna)] = $coluna;
        }
        $this->linhas = array_map(function ($linha) use ($mapa) {
            $nova = [];
            foreach ($linha as $chave => $valor) $nova[$mapa[$chave] ?? $chave] = $valor;
            return $nova;
        }, $linhas);
        $this->num_rows = count($this->linhas);
    }

    public function fetch_assoc(): ?array
    {
        return $this->linhas[$this->posicao++] ?? null;
    }
}

/** Pequena camada sobre o PDO. Sempre use "?" nos valores para evitar SQL Injection. */
class Banco
{
    public PDO $pdo;
    public string $error = '';

    public function __construct(string $url)
    {
        $p = parse_url($url);
        if (!$p || empty($p['host'])) {
            throw new RuntimeException('DATABASE_URL inválida ou não configurada.');
        }
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=require',
            $p['host'],
            $p['port'] ?? 5432,
            ltrim($p['path'] ?? '/postgres', '/') ?: 'postgres'
        );
        $this->pdo = new PDO($dsn, urldecode($p['user'] ?? ''), urldecode($p['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Compatível com o pooler do Supabase (que não aceita prepared statements no servidor)
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
        $this->pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
    }

    /**
     * Executa um comando SQL com parâmetros.
     * Retorna Resultado (para SELECT ou ... RETURNING) ou false em caso de erro.
     */
    public function query(string $sql, array $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($params));
            $linhas = $stmt->columnCount() > 0 ? $stmt->fetchAll() : [];
            $resultado = new Resultado($linhas);
            if ($stmt->columnCount() === 0) $resultado->num_rows = $stmt->rowCount();
            return $resultado;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log('[Acesso+] Erro SQL: ' . $e->getMessage());
            return false;
        }
    }
}

try {
    $con = new Banco(getenv('DATABASE_URL') ?: '');
} catch (Throwable $e) {
    error_log('[Acesso+] Falha ao conectar no banco: ' . $e->getMessage());
    $con = null;
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'api_app.php') {
        http_response_code(500);
        exit('Erro ao conectar ao banco de dados. Verifique a variável DATABASE_URL.');
    }
}

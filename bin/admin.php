<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\ConnectionFactory;
use App\Core\Environment;
use App\Core\LoggerFactory;
use App\Exceptions\AdministratorBootstrapException;
use App\Repositories\AdministratorBootstrapRepository;
use App\Requests\AdministratorBootstrapRequest;
use App\Services\AdministratorBootstrapService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Administrator command unavailable.\n");
    exit(1);
}

require $autoloadPath;

$usage = static function (): void {
    fwrite(STDOUT, <<<TXT
        Usage:
          php bin/admin.php help
          php bin/admin.php bootstrap [options]

        Bootstrap options:
          --full-name <value>
          --username <value>
          --email <value>
          --phone <value>
          --password-stdin
          --force
          --help

        Passwords are accepted only through protected terminal input or --password-stdin.
        TXT . "\n");
};

$arguments = array_slice($argv, 1);
$command = array_shift($arguments);

if ($command === 'help') {
    if ($arguments !== []) {
        fwrite(STDERR, "Unknown Administrator command option.\n");
        exit(2);
    }

    $usage();
    exit(0);
}

if ($command !== 'bootstrap') {
    fwrite(STDERR, "Unknown Administrator command.\n");
    exit(2);
}

$valueOptions = ['full-name', 'username', 'email', 'phone'];
$flagOptions = ['password-stdin', 'force', 'help'];
$options = [];

while ($arguments !== []) {
    $argument = array_shift($arguments);

    if (!is_string($argument) || !str_starts_with($argument, '--')) {
        fwrite(STDERR, "Unknown Administrator command option.\n");
        exit(2);
    }

    $option = substr($argument, 2);
    $inlineValue = null;

    if (str_contains($option, '=')) {
        [$option, $inlineValue] = explode('=', $option, 2);
    }

    if (!in_array($option, array_merge($valueOptions, $flagOptions), true)) {
        fwrite(STDERR, "Unknown Administrator command option.\n");
        exit(2);
    }

    if (array_key_exists($option, $options)) {
        fwrite(STDERR, "Duplicate Administrator command option.\n");
        exit(2);
    }

    if (in_array($option, $flagOptions, true)) {
        if ($inlineValue !== null) {
            fwrite(STDERR, "Administrator command flag does not accept a value.\n");
            exit(2);
        }

        $options[$option] = true;
        continue;
    }

    if ($inlineValue === null) {
        $next = array_shift($arguments);

        if (!is_string($next) || str_starts_with($next, '--')) {
            fwrite(STDERR, "Administrator command option requires a value.\n");
            exit(2);
        }

        $inlineValue = $next;
    }

    if ($inlineValue === '') {
        fwrite(STDERR, "Administrator command option requires a value.\n");
        exit(2);
    }

    $options[$option] = $inlineValue;
}

if (isset($options['help'])) {
    if (count($options) !== 1) {
        fwrite(STDERR, "Invalid Administrator command option combination.\n");
        exit(2);
    }

    $usage();
    exit(0);
}

$force = isset($options['force']);
$passwordFromStdin = isset($options['password-stdin']);

$readVisible = static function (string $label): string {
    if (!stream_isatty(STDIN)) {
        throw AdministratorBootstrapException::usage('Required interactive input is unavailable.');
    }

    fwrite(STDOUT, $label . ': ');
    $value = fgets(STDIN);

    if (!is_string($value)) {
        throw AdministratorBootstrapException::usage('Required interactive input is unavailable.');
    }

    return rtrim($value, "\r\n");
};

$readPosixSecret = static function (string $label): string {
    if (!stream_isatty(STDIN) || !function_exists('exec')) {
        throw AdministratorBootstrapException::usage('Secure password input is unavailable.');
    }

    $stateOutput = [];
    $stateCode = 1;
    exec('stty -g', $stateOutput, $stateCode);
    $state = $stateOutput[0] ?? null;

    if ($stateCode !== 0 || !is_string($state) || preg_match('/^[A-Za-z0-9:;]+$/D', $state) !== 1) {
        throw AdministratorBootstrapException::usage('Secure password input is unavailable.');
    }

    $disableCode = 1;
    exec('stty -echo', result_code: $disableCode);

    if ($disableCode !== 0) {
        throw AdministratorBootstrapException::usage('Secure password input is unavailable.');
    }

    try {
        fwrite(STDOUT, $label . ': ');
        $value = fgets(STDIN);
        fwrite(STDOUT, "\n");

        if (!is_string($value)) {
            throw AdministratorBootstrapException::usage('Secure password input is unavailable.');
        }

        return rtrim($value, "\r\n");
    } finally {
        exec('stty ' . escapeshellarg($state));
    }
};

$readWindowsSecret = static function (string $label): string {
    if (!stream_isatty(STDIN) || !function_exists('proc_open')) {
        throw AdministratorBootstrapException::usage('Secure password input is unavailable.');
    }

    $prompt = base64_encode($label);
    $script = '$label=[Text.Encoding]::UTF8.GetString([Convert]::FromBase64String(' . var_export($prompt, true) . '));'
        . '$secret=Read-Host -Prompt $label -AsSecureString;'
        . '$pointer=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret);'
        . 'try {[Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer))}'
        . 'finally {[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)}';

    foreach (['pwsh.exe', 'powershell.exe'] as $executable) {
        $pipes = [];
        $process = @proc_open(
            [$executable, '-NoLogo', '-NoProfile', '-Command', $script],
            [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            continue;
        }

        $value = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode === 0 && is_string($value)) {
            return $value;
        }
    }

    throw AdministratorBootstrapException::usage('Secure password input is unavailable.');
};

$readProtectedStdin = static function (): array {
    if (stream_isatty(STDIN)) {
        throw AdministratorBootstrapException::usage('Secure password input is unavailable.');
    }

    $input = stream_get_contents(STDIN);

    if (
        !is_string($input)
        || preg_match('/\A([^\r\n]*)\r?\n([^\r\n]*)(?:\r?\n)?\z/Ds', $input, $matches) !== 1
    ) {
        throw AdministratorBootstrapException::usage('Protected password input is invalid.');
    }

    return [$matches[1], $matches[2]];
};

try {
    foreach ([
        'BASE_PATH' => $basePath,
        'STORAGE_PATH' => $basePath . DIRECTORY_SEPARATOR . 'storage',
        'PUBLIC_PATH' => $basePath . DIRECTORY_SEPARATOR . 'public',
    ] as $constant => $path) {
        if (defined($constant) && constant($constant) !== $path) {
            throw AdministratorBootstrapException::failure();
        }

        if (!defined($constant) && !define($constant, $path)) {
            throw AdministratorBootstrapException::failure();
        }
    }

    $environment = Environment::load($basePath);
    $environmentName = $environment->literalString('APP_ENV', 'local');
    $securityConfiguration = Config::loadSecurity(
        $basePath . DIRECTORY_SEPARATOR . 'config',
        $environment,
    );
    $security = $securityConfiguration['administrator_bootstrap'] ?? null;

    if (!is_array($security)) {
        throw AdministratorBootstrapException::failure();
    }

    AdministratorBootstrapService::assertEnvironmentPolicy($security, $environmentName, $force);

    $configuration = Config::load($basePath . DIRECTORY_SEPARATOR . 'config', $environment);
    $database = $configuration->require('database');

    if (!is_array($database)) {
        throw AdministratorBootstrapException::failure();
    }

    $logger = LoggerFactory::create($configuration);
    $service = new AdministratorBootstrapService(
        static fn (): AdministratorBootstrapRepository => new AdministratorBootstrapRepository(
            (new ConnectionFactory($database))->create(),
        ),
        $logger,
        $security,
    );

    if ($passwordFromStdin) {
        foreach (['full-name', 'username', 'email'] as $requiredOption) {
            if (!isset($options[$requiredOption])) {
                throw AdministratorBootstrapException::usage(
                    'Identity options are required with --password-stdin.',
                );
            }
        }
    }

    $fullName = isset($options['full-name'])
        ? (string) $options['full-name']
        : $readVisible('Full name');
    $username = isset($options['username'])
        ? (string) $options['username']
        : $readVisible('Username');
    $email = isset($options['email'])
        ? (string) $options['email']
        : $readVisible('Email');
    $phone = isset($options['phone'])
        ? (string) $options['phone']
        : ($passwordFromStdin ? null : $readVisible('Phone (optional)'));

    if ($passwordFromStdin) {
        [$password, $confirmation] = $readProtectedStdin();
    } elseif (PHP_OS_FAMILY === 'Windows') {
        $password = $readWindowsSecret('Password');
        $confirmation = $readWindowsSecret('Confirm password');
    } else {
        $password = $readPosixSecret('Password');
        $confirmation = $readPosixSecret('Confirm password');
    }

    $request = AdministratorBootstrapRequest::fromInput(
        $fullName,
        $username,
        $email,
        $phone,
        $password,
        $confirmation,
        $security,
    );
    $service->bootstrap($request, $environmentName, $force);
    fwrite(STDOUT, "Administrator account created.\n");
    exit(0);
} catch (AdministratorBootstrapException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit($exception->exitCode());
} catch (Throwable) {
    fwrite(STDERR, "Administrator bootstrap failed.\n");
    exit(1);
}

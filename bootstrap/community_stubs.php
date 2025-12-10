<?php
declare(strict_types=1);

namespace UniPanel\Community;

function build_stub_content(string $view, bool $isPublic = false): string
{
    $baseLine = $isPublic
        ? '$communityBasePath = dirname(__DIR__);'
        : '$communityBasePath = __DIR__;';

    $entryPath = $isPublic
        ? "__DIR__ . '/../../../bootstrap/community_entry.php'"
        : "__DIR__ . '/../../bootstrap/community_entry.php'";

    return <<<PHP
<?php
{$baseLine}
\$communityView = '{$view}';
require_once {$entryPath};

PHP;
}

function write_stub(string $filePath, string $view, bool $isPublic = false): bool
{
    $directory = dirname($filePath);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            return false;
        }
        @chmod($directory, 0777);
    }

    $content = build_stub_content($view, $isPublic);
    $result = file_put_contents($filePath, $content);

    if ($result === false) {
        return false;
    }

    @chmod($filePath, 0666);
    return true;
}

function sync_community_stubs(string $communityPath): array
{
    $communityPath = rtrim($communityPath, "/\\");
    if (!is_dir($communityPath)) {
        return [
            'success' => false,
            'errors' => ['Topluluk klasörü bulunamadı: ' . $communityPath],
            'written' => [],
        ];
    }

    $mapping = [
        $communityPath . '/index.php' => ['view' => 'index', 'public' => false],
        $communityPath . '/login.php' => ['view' => 'login', 'public' => false],
        $communityPath . '/loading.php' => ['view' => 'loading', 'public' => false],
        $communityPath . '/public/index.php' => ['view' => 'public_index', 'public' => true],
    ];

    $written = [];
    $errors = [];

    foreach ($mapping as $file => $info) {
        $success = write_stub($file, $info['view'], $info['public']);
        if ($success) {
            $written[] = $file;
        } else {
            $errors[] = $file;
        }
    }

    return [
        'success' => empty($errors),
        'written' => $written,
        'errors' => $errors,
    ];
}


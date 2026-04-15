<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProjectSnapshot extends Model
{
    protected $fillable = [
        'project_id',
        'version',
        'file_path',
        'file_size',
        'file_count',
        'file_tree',
        'install_notes',
        'exclude_patterns',
        'change_note',
        'source',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_tree' => 'array',
            'exclude_patterns' => 'array',
            'file_size' => 'integer',
            'file_count' => 'integer',
        ];
    }

    /**
     * Default exclude patterns for file tree building.
     */
    public const DEFAULT_EXCLUDES = [
        'node_modules/',
        'vendor/',
        '.git/',
        '.env',
        'storage/logs/',
        'storage/framework/',
        '__pycache__/',
        '.DS_Store',
        'Thumbs.db',
        '*.log',
        '.idea/',
        '.vscode/',
        'bootstrap/cache/',
        'public/build/',
        'public/hot',
        '*.sqlite',
        '*.sqlite-journal',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Build file tree from a ZIP archive.
     * Returns [tree, fileCount, totalSize] without extracting.
     */
    public static function buildFileTreeFromZip(string $zipPath, array $excludePatterns = []): array
    {
        $patterns = array_merge(self::DEFAULT_EXCLUDES, $excludePatterns);
        $tree = [];
        $fileCount = 0;

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [[], 0];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            // Skip excluded patterns
            if (self::shouldExclude($name, $patterns)) {
                continue;
            }

            // Skip directories (they're implied by file paths)
            if (str_ends_with($name, '/')) {
                continue;
            }

            $fileCount++;
            self::insertIntoTree($tree, $name, $stat['size']);
        }

        $zip->close();

        // Sort tree
        self::sortTree($tree);

        return [$tree, $fileCount];
    }

    /**
     * Read a single file's content from the ZIP (for preview).
     */
    public function readFileFromZip(string $filePath): ?string
    {
        $fullPath = Storage::disk('local')->path($this->file_path);

        $zip = new ZipArchive();
        if ($zip->open($fullPath) !== true) {
            return null;
        }

        $content = $zip->getFromName($filePath);
        $zip->close();

        return $content !== false ? $content : null;
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Get the storage path for a project's snapshots.
     */
    public static function storagePath(int $projectId): string
    {
        return "project-snapshots/{$projectId}";
    }

    private static function shouldExclude(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            // Directory pattern (ends with /)
            if (str_ends_with($pattern, '/')) {
                $dir = rtrim($pattern, '/');
                if (str_contains($path, $dir . '/')) {
                    return true;
                }
            }
            // Wildcard pattern
            elseif (str_starts_with($pattern, '*.')) {
                $ext = substr($pattern, 1);
                if (str_ends_with($path, $ext)) {
                    return true;
                }
            }
            // Exact match
            else {
                $basename = basename($path);
                if ($basename === $pattern) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function insertIntoTree(array &$tree, string $path, int $size): void
    {
        $parts = explode('/', $path);
        $current = &$tree;

        foreach ($parts as $i => $part) {
            if ($part === '' || $part === '.') continue;

            $isFile = ($i === count($parts) - 1);

            // Find existing node
            $found = false;
            foreach ($current as &$node) {
                if ($node['name'] === $part) {
                    if (!$isFile && isset($node['children'])) {
                        $current = &$node['children'];
                        $found = true;
                        break;
                    }
                }
            }
            unset($node);

            if (!$found) {
                if ($isFile) {
                    $current[] = [
                        'name' => $part,
                        'size' => $size,
                        'type' => 'file',
                    ];
                } else {
                    $current[] = [
                        'name' => $part,
                        'type' => 'dir',
                        'children' => [],
                    ];
                    $current = &$current[count($current) - 1]['children'];
                }
            }
        }
    }

    private static function sortTree(array &$tree): void
    {
        usort($tree, function ($a, $b) {
            // Directories first
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        foreach ($tree as &$node) {
            if ($node['type'] === 'dir' && !empty($node['children'])) {
                self::sortTree($node['children']);
            }
        }
    }
}

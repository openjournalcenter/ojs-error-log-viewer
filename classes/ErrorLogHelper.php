<?php

namespace APP\plugins\generic\errorLogViewer\classes;

use APP\core\Application;
use PKP\config\Config;
use PKP\core\Core;

class ErrorLogHelper
{
	public static function resolveLogPath(?string $customPath = null): ?string
	{
		if (!empty($customPath)) {
			$cleanCustom = trim($customPath);
			$realPath = realpath($cleanCustom);
			$extension = strtolower(pathinfo($cleanCustom, PATHINFO_EXTENSION));
			$basename = strtolower(basename($cleanCustom));
			$isLogFilename = in_array($extension, ['log', 'txt']) || str_contains($basename, 'log');

			if ($realPath && file_exists($realPath) && is_readable($realPath) && !is_dir($realPath) && $isLogFilename) {
				if ($realPath !== realpath(Config::getConfigFileName())) {
					return $realPath;
				}
			}
		}

		$phpIniLog = ini_get('error_log');
		if (!empty($phpIniLog) && file_exists($phpIniLog) && is_readable($phpIniLog)) {
			return $phpIniLog;
		}

		$baseDir = Core::getBaseDir();
		$filesDir = Config::getVar('files', 'files_dir');
		$parentDir = dirname($baseDir);

		$candidates = [
			$baseDir . '/error_log',
			$baseDir . '/error.log',
			$baseDir . '/php_error.log',
			$baseDir . '/php_errors.log',
			$baseDir . '/public_html/error_log',
			$filesDir . '/error_log',
			$filesDir . '/error.log',
			$parentDir . '/error_log',
			$parentDir . '/logs/error.log',
			$parentDir . '/logs/error_log',
			$parentDir . '/logs/php_error.log',
		];

		$tempDir = sys_get_temp_dir();
		if (!empty($tempDir)) {
			$candidates[] = rtrim($tempDir, '/\\') . '/php_errors.log';
			$candidates[] = rtrim($tempDir, '/\\') . '/error_log';
		}

		if (defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Linux') {
			$phpVersionMajorMinor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
			$candidates[] = '/var/log/php' . $phpVersionMajorMinor . '-fpm.log';
			$candidates[] = '/var/log/php-fpm.log';
			$candidates[] = '/var/log/php-fpm/error.log';
			$candidates[] = '/var/log/php-fpm/www-error.log';
			$candidates[] = '/var/log/apache2/error.log';
			$candidates[] = '/var/log/httpd/error_log';
			$candidates[] = '/var/log/nginx/error.log';
			$candidates[] = '/home/' . get_current_user() . '/logs/error.log';
			$candidates[] = '/home/' . get_current_user() . '/error_log';
		}

		$validCandidates = [];
		foreach ($candidates as $path) {
			if (!empty($path) && file_exists($path) && is_readable($path) && !is_dir($path)) {
				$validCandidates[] = [
					'path' => $path,
					'mtime' => filemtime($path),
					'size' => filesize($path),
				];
			}
		}

		if (!empty($validCandidates)) {
			usort($validCandidates, function ($a, $b) {
				return $b['mtime'] <=> $a['mtime'];
			});
			return $validCandidates[0]['path'];
		}

		return !empty($phpIniLog) ? $phpIniLog : ($baseDir . '/error_log');
	}

	public static function readTail(string $filePath, int $lines = 250): array
	{
		if (!file_exists($filePath) || !is_readable($filePath)) {
			return [];
		}

		$handle = fopen($filePath, 'rb');
		if (!$handle) {
			return [];
		}

		$bufferSize = 4096;
		$output = '';
		fseek($handle, 0, SEEK_END);
		$fileSize = ftell($handle);
		$pos = $fileSize;
		$lineCount = 0;

		while ($pos > 0 && $lineCount <= $lines) {
			$readLength = min($bufferSize, $pos);
			$pos -= $readLength;
			fseek($handle, $pos, SEEK_SET);
			$chunk = fread($handle, $readLength);
			$output = $chunk . $output;
			$lineCount = substr_count($output, "\n");
		}

		fclose($handle);

		if (preg_match('/[^\x20-\x7E\t\r\n]/', $output)) {
			$converted = @mb_convert_encoding($output, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
			if ($converted !== false) {
				$output = $converted;
			}
		}

		$allLines = explode("\n", trim($output));
		if (count($allLines) > $lines) {
			$allLines = array_slice($allLines, -$lines);
		}

		$parsed = [];
		foreach ($allLines as $rawLine) {
			$line = trim($rawLine);
			if (empty($line)) {
				continue;
			}

			$level = 'other';
			if (preg_match('/fatal error|uncaught exception|parse error/i', $line)) {
				$level = 'fatal';
			} elseif (preg_match('/warning/i', $line)) {
				$level = 'warning';
			} elseif (preg_match('/notice|deprecated/i', $line)) {
				$level = 'notice';
			}

			$parsed[] = [
				'raw' => $line,
				'level' => $level,
			];
		}

		return $parsed;
	}

	public static function getDiagnosticsSummary(array $parsedLines, int $limit = 20): string
	{
		$fatalOrWarnings = array_filter($parsedLines, function ($item) {
			return in_array($item['level'], ['fatal', 'warning']);
		});

		if (empty($fatalOrWarnings)) {
			$fatalOrWarnings = array_slice($parsedLines, -$limit);
		} else {
			$fatalOrWarnings = array_slice($fatalOrWarnings, -$limit);
		}

		$summary = [];
		$summary[] = '=== OJS SYSTEM DIAGNOSTICS LOG SUMMARY ===';
		$summary[] = 'Timestamp: ' . date('Y-m-d H:i:s');
		$summary[] = 'PHP Version: ' . PHP_VERSION;
		$summary[] = 'OJS Version: ' . Application::get()->getCurrentVersion()->getVersionString();
		$summary[] = 'OS: ' . PHP_OS;
		$summary[] = '------------------------------------------';

		foreach ($fatalOrWarnings as $item) {
			$summary[] = $item['raw'];
		}

		return implode(PHP_EOL, $summary);
	}

	public static function formatBytes(int $bytes): string
	{
		if ($bytes <= 0) {
			return '0 B';
		}
		$units = ['B', 'KB', 'MB', 'GB'];
		$i = (int) floor(log($bytes, 1024));
		$unit = $units[$i] ?? 'B';
		return round($bytes / pow(1024, $i), 2) . ' ' . $unit;
	}
}

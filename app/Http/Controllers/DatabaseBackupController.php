<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

class DatabaseBackupController extends Controller
{
    public function index()
    {
        return inertia('Settings/Backup');
    }

    public function download()
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        $filename = "backup-" . $database . "-" . date('Y-m-d-H-i-s') . ".sql";

        // Construct the mysqldump command
        $command = sprintf(
            'MYSQL_PWD=%s mysqldump --host=%s --port=%s --user=%s --no-tablespaces --skip-ssl %s',
            escapeshellarg($password),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database)
        );

        return new StreamedResponse(function () use ($command) {
            $handle = popen($command, 'r');
            if ($handle === false) {
                echo "/* Error executing mysqldump */";
                return;
            }

            while (!feof($handle)) {
                echo fread($handle, 4096);
                flush();
            }
            pclose($handle);
        }, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIncomingEmail;
use App\Models\Inbox;
use Illuminate\Console\Command;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class SmtpServerCommand extends Command
{
    protected $signature = 'smtp:serve 
                            {--host=0.0.0.0 : The host to bind to}
                            {--port=2525 : The port to listen on}';

    protected $description = 'Start the SMTP server to receive emails';

    private array $sessions = [];

    public function handle(): int
    {
        $host = $this->option('host') ?? env('SMTP_SERVER_HOST', '0.0.0.0');
        $port = $this->option('port') ?? env('SMTP_SERVER_PORT', 2525);

        $this->info("Starting SMTP server on {$host}:{$port}...");
        $this->info('Press Ctrl+C to stop');

        $loop = Loop::get();
        
        try {
            $socket = new SocketServer("{$host}:{$port}", [], $loop);
            
            $socket->on('connection', function (ConnectionInterface $connection) {
                $this->handleConnection($connection);
            });

            $socket->on('error', function (\Exception $e) {
                $this->error("Socket error: " . $e->getMessage());
            });

            $this->info("✓ SMTP server listening on {$host}:{$port}");
            $this->info("Ready to receive emails...\n");

            $loop->run();

        } catch (\Exception $e) {
            $this->error("Failed to start SMTP server: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function handleConnection(ConnectionInterface $connection): void
    {
        $remoteAddress = $connection->getRemoteAddress();
        $sessionId = spl_object_hash($connection);
        
        $this->sessions[$sessionId] = [
            'state' => 'INIT',
            'from' => null,
            'to' => [],
            'data' => '',
            'reading_data' => false,
            'closed' => false,
        ];

        $this->info("[{$remoteAddress}] New connection");

        // Send greeting
        $connection->write("220 tempmail.local ESMTP Ready\r\n");

        $buffer = '';

        $connection->on('data', function ($data) use ($connection, $sessionId, &$buffer, $remoteAddress) {
            // Check if session still exists
            if (!isset($this->sessions[$sessionId]) || $this->sessions[$sessionId]['closed']) {
                return;
            }
            
            $buffer .= $data;
            
            // Process complete lines
            while (($pos = strpos($buffer, "\r\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);
                
                // Check again before processing
                if (!isset($this->sessions[$sessionId])) {
                    return;
                }
                
                $this->processLine($connection, $sessionId, $line, $remoteAddress);
            }
            
            // Handle data mode (email content)
            if (isset($this->sessions[$sessionId]) && 
                $this->sessions[$sessionId]['reading_data'] && $buffer) {
                $this->sessions[$sessionId]['data'] .= $buffer;
                $buffer = '';
            }
        });

        $connection->on('close', function () use ($sessionId, $remoteAddress) {
            if (isset($this->sessions[$sessionId])) {
                $this->sessions[$sessionId]['closed'] = true;
            }
            $this->info("[{$remoteAddress}] Connection closed");
            unset($this->sessions[$sessionId]);
        });

        $connection->on('error', function (\Exception $e) use ($sessionId, $remoteAddress) {
            $this->error("[{$remoteAddress}] Error: " . $e->getMessage());
            if (isset($this->sessions[$sessionId])) {
                $this->sessions[$sessionId]['closed'] = true;
            }
        });
    }

    private function processLine(ConnectionInterface $connection, string $sessionId, string $line, string $remoteAddress): void
    {
        // Safety check
        if (!isset($this->sessions[$sessionId])) {
            return;
        }
        
        $session = &$this->sessions[$sessionId];

        // If we're reading email data
        if ($session['reading_data']) {
            // End of data marker
            if ($line === '.') {
                $session['reading_data'] = false;
                
                // Remove the trailing \r\n. from data
                $rawEmail = rtrim($session['data'], "\r\n");
                
                // Process each recipient
                foreach ($session['to'] as $toEmail) {
                    // Check if inbox exists
                    $inbox = Inbox::findByEmail($toEmail);
                    
                    if ($inbox) {
                        $this->info("[{$remoteAddress}] Queueing email to: {$toEmail}");
                        
                        // Dispatch job to process email
                        ProcessIncomingEmail::dispatch($rawEmail, $toEmail, $session['from']);
                    } else {
                        $this->warn("[{$remoteAddress}] No inbox found for: {$toEmail}");
                    }
                }
                
                $connection->write("250 OK: Message queued\r\n");
                
                // Reset session for next message
                $session['from'] = null;
                $session['to'] = [];
                $session['data'] = '';
                
                return;
            }
            
            // Handle dot-stuffing (lines starting with . that aren't the end marker)
            if (str_starts_with($line, '.')) {
                $line = substr($line, 1);
            }
            
            $session['data'] .= $line . "\r\n";
            return;
        }

        // Parse SMTP commands
        $parts = explode(' ', $line, 2);
        $command = strtoupper($parts[0]);
        $args = $parts[1] ?? '';

        switch ($command) {
            case 'HELO':
            case 'EHLO':
                $connection->write("250-tempmail.local Hello\r\n");
                $connection->write("250-SIZE 10485760\r\n");
                $connection->write("250 OK\r\n");
                $session['state'] = 'READY';
                break;

            case 'MAIL':
                if (preg_match('/FROM:\s*<([^>]*)>/i', $args, $matches)) {
                    $session['from'] = $matches[1];
                    $connection->write("250 OK\r\n");
                    $this->info("[{$remoteAddress}] MAIL FROM: {$session['from']}");
                } else {
                    $connection->write("501 Syntax error in parameters\r\n");
                }
                break;

            case 'RCPT':
                if (preg_match('/TO:\s*<([^>]*)>/i', $args, $matches)) {
                    $toEmail = strtolower($matches[1]);
                    $session['to'][] = $toEmail;
                    $connection->write("250 OK\r\n");
                    $this->info("[{$remoteAddress}] RCPT TO: {$toEmail}");
                } else {
                    $connection->write("501 Syntax error in parameters\r\n");
                }
                break;

            case 'DATA':
                if (empty($session['to'])) {
                    $connection->write("503 No recipients specified\r\n");
                } else {
                    $connection->write("354 Start mail input; end with <CRLF>.<CRLF>\r\n");
                    $session['reading_data'] = true;
                    $session['data'] = '';
                }
                break;

            case 'RSET':
                $session['from'] = null;
                $session['to'] = [];
                $session['data'] = '';
                $session['reading_data'] = false;
                $connection->write("250 OK\r\n");
                break;

            case 'NOOP':
                $connection->write("250 OK\r\n");
                break;

            case 'QUIT':
                $connection->write("221 Bye\r\n");
                $session['closed'] = true;
                $connection->close();
                break;

            case 'VRFY':
            case 'EXPN':
                $connection->write("252 Cannot verify user\r\n");
                break;

            default:
                $connection->write("500 Command not recognized\r\n");
                break;
        }
    }
}

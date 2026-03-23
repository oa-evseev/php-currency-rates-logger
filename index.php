<?php

require_once(__DIR__ . '/run.php');

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = run_currency_update(false);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Currency Update Utility</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f7f7f7;
            color: #222;
        }

        .box {
            max-width: 900px;
            background: #fff;
            border: 1px solid #ccc;
            padding: 24px;
        }

        h1 {
            margin-top: 0;
        }

        button {
            padding: 10px 18px;
            font-size: 16px;
            cursor: pointer;
        }

        .result {
            margin-top: 24px;
            padding: 16px;
            border: 1px solid #ccc;
            background: #fafafa;
        }

        .ok {
            border-left: 5px solid #2e7d32;
        }

        .error {
            border-left: 5px solid #c62828;
        }

        table {
            border-collapse: collapse;
            margin-top: 12px;
            width: 100%;
        }

        th, td {
            text-align: left;
            border: 1px solid #ddd;
            padding: 8px;
            vertical-align: top;
        }

        th {
            width: 220px;
            background: #f0f0f0;
        }

        code {
            background: #f0f0f0;
            padding: 2px 4px;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>Currency Update Utility</h1>

        <form method="post">
            <button type="submit">Run update</button>
        </form>

        <?php if ($result !== null): ?>
            <div class="result <?php echo $result['success'] ? 'ok' : 'error'; ?>">
                <h2>Result</h2>

                <table>
                    <tr>
                        <th>Success</th>
                        <td><?php echo $result['success'] ? 'yes' : 'no'; ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><?php echo htmlspecialchars((string) $result['status']); ?></td>
                    </tr>
                    <tr>
                        <th>Message</th>
                        <td><?php echo htmlspecialchars((string) $result['message']); ?></td>
                    </tr>

                    <?php if (isset($result['date'])): ?>
                        <tr>
                            <th>Source date</th>
                            <td><?php echo htmlspecialchars((string) $result['date']); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (isset($result['last_date_in_db'])): ?>
                        <tr>
                            <th>Previous DB date</th>
                            <td><?php echo htmlspecialchars((string) $result['last_date_in_db']); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (isset($result['saved_count'])): ?>
                        <tr>
                            <th>Saved rows</th>
                            <td><?php echo (int) $result['saved_count']; ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (isset($result['mail_sent'])): ?>
                        <tr>
                            <th>Mail sent</th>
                            <td><?php echo $result['mail_sent'] ? 'yes' : 'no'; ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (!empty($result['providers'])): ?>
                        <tr>
                            <th>Custom providers</th>
                            <td><?php echo htmlspecialchars(implode(', ', $result['providers'])); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (!empty($result['tracked_currencies'])): ?>
                        <tr>
                            <th>Tracked currencies</th>
                            <td><?php echo htmlspecialchars(implode(', ', $result['tracked_currencies'])); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>

        <p style="margin-top: 24px;">
            Cron target: <code>/usr/local/bin/php /path/to/php-currency-rates-logger/run.php</code>
        </p>
    </div>
</body>
</html>

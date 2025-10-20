<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Logging Demo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            width: 100%;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }

        .header h1 {
            font-size: 3em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .demos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .demo-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }

        .demo-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 80px rgba(0,0,0,0.4);
        }

        .demo-card h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.5em;
        }

        .demo-card p {
            color: #666;
            margin-bottom: 20px;
            flex-grow: 1;
            line-height: 1.6;
        }

        .demo-card a {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.3s ease;
            text-align: center;
        }

        .demo-card a:hover {
            opacity: 0.9;
        }

        .info-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            color: #333;
            line-height: 1.8;
        }

        .info-box h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        .info-box p {
            margin-bottom: 12px;
        }

        .info-box code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #d63384;
        }

        .log-path {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            font-family: monospace;
            font-size: 0.9em;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Laravel Logging Demo</h1>
            <p>Superlog - Advanced Structured Logging for Laravel</p>
        </div>

        <div class="demos-grid">
            @foreach($demos as $demo)
            <div class="demo-card">
                <h3>{{ $demo['name'] }}</h3>
                <p>{{ $demo['description'] }}</p>
                <a href="{{ $demo['url'] }}">Test Now →</a>
            </div>
            @endforeach
        </div>

        <div class="info-box">
            <h3>📝 About This Demo</h3>
            <p>This is a demonstration Laravel application configured with the <code>laravel-logging</code> (Superlog) package.</p>
            <p><strong>Log Files Location:</strong></p>
            <div class="log-path">storage/logs/</div>
            <p style="margin-top: 15px;"><strong>Key Features:</strong></p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>✓ Structured JSON logging</li>
                <li>✓ Trace ID tracking</li>
                <li>✓ Request sequence numbering</li>
                <li>✓ Context data logging</li>
                <li>✓ Multiple format support</li>
                <li>✓ Stack trace inclusion</li>
            </ul>
            <p style="margin-top: 15px;"><strong>Available Commands:</strong></p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li><code>php artisan superlog:check</code> - Check configuration</li>
                <li><code>php artisan superlog:check --test</code> - Write test entry</li>
                <li><code>php artisan superlog:check --diagnostics</code> - Run diagnostics</li>
            </ul>
        </div>
    </div>
</body>
</html>
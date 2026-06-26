<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>401 — Unauthorized | AI Recruit</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #0f0f1a;
            color: #e2e8f0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .error-card {
            text-align: center;
            max-width: 480px;
            width: 100%;
            background: #1a1a2e;
            border: 1px solid rgba(251, 191, 36, 0.2);
            border-radius: 20px;
            padding: 56px 40px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
        }
        .error-code {
            font-size: 5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 12px;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 12px;
        }
        .error-desc {
            font-size: 0.9375rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .info-box {
            background: rgba(251, 191, 36, 0.06);
            border: 1px solid rgba(251, 191, 36, 0.15);
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 0.8125rem;
            color: #fbbf24;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .btn-group { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-primary {
            padding: 11px 24px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.2s;
            display: inline-block;
        }
        .btn-primary:hover { opacity: 0.88; }
        .btn-secondary {
            padding: 11px 24px;
            background: rgba(79, 70, 229, 0.1);
            border: 1px solid rgba(79, 70, 229, 0.3);
            border-radius: 8px;
            color: #a5b4fc;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
            display: inline-block;
        }
        .btn-secondary:hover { background: rgba(79, 70, 229, 0.18); }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="error-icon">🔐</span>
        <div class="error-code">401</div>
        <h1 class="error-title">Unauthorized</h1>
        <p class="error-desc">
            You need to be signed in to access this resource.
        </p>
        <div class="info-box">
            Your session may have expired. Please sign in again to continue.
        </div>
        <div class="btn-group">
            <a href="/login" class="btn-primary">Sign In</a>
            <a href="/" class="btn-secondary">Home</a>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'AI Recruit') ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #0d0d20 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #e2e8f0;
        }
        .auth-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 24px;
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .auth-logo .icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 12px;
        }
        .auth-logo .brand {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }
        .auth-logo .tagline {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-top: 4px;
        }
        .auth-card {
            background: #1a1a2e;
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(79, 70, 229, 0.1);
        }
        .auth-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.75rem;
            color: #64748b;
        }
        .auth-footer a { color: #4f46e5; text-decoration: none; }
        .auth-footer a:hover { text-decoration: underline; }
        /* Form elements */
        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #cbd5e1;
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: #0f0f1a;
            border: 1px solid rgba(79, 70, 229, 0.3);
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 0.9375rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }
        .form-input::placeholder { color: #475569; }
        .btn-primary {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            letter-spacing: 0.01em;
        }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-primary:active { transform: translateY(0); }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 12px 16px;
            color: #fca5a5;
            font-size: 0.875rem;
            margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 8px;
            padding: 12px 16px;
            color: #86efac;
            font-size: 0.875rem;
            margin-bottom: 20px;
        }
        .link-muted {
            color: #94a3b8;
            font-size: 0.875rem;
            text-decoration: none;
        }
        .link-muted:hover { color: #4f46e5; }
        .link-primary {
            color: #4f46e5;
            font-size: 0.875rem;
            text-decoration: none;
            font-weight: 500;
        }
        .link-primary:hover { color: #7c3aed; }
        .divider {
            border: none;
            border-top: 1px solid rgba(79, 70, 229, 0.15);
            margin: 24px 0;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-logo">
            <span class="icon">🤖</span>
            <div class="brand">AI Recruit</div>
            <div class="tagline">Intelligent Hiring Platform</div>
        </div>
        <div class="auth-card">
            <?= $content ?? '' ?>
        </div>
        <div class="auth-footer">
            &copy; <?= date('Y') ?> AI Recruit &mdash; <a href="#">Privacy</a> &middot; <a href="#">Terms</a>
        </div>
    </div>
</body>
</html>

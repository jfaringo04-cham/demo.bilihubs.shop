<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email | BiliHub</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css'])

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f7f7f8;
            font-family: Arial, sans-serif;
            color: #202124;
        }

        .verification-card {
            width: min(420px, calc(100% - 32px));
            box-sizing: border-box;
            padding: 36px 30px;
            background: white;
            border: 1px solid #e7e7e7;
            border-radius: 16px;
            text-align: center;
        }

        .brand {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 24px;
        }

        .description {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }

        .email {
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .otp-input {
            width: 100%;
            box-sizing: border-box;
            margin: 22px 0 14px;
            padding: 16px;
            border: 1px solid #ccc;
            border-radius: 10px;
            font-size: 28px;
            letter-spacing: 8px;
            text-align: center;
        }

        .primary-button {
            width: 100%;
            padding: 14px;
            background: #171717;
            color: white;
            border: 0;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .primary-button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .resend-button {
            margin-top: 20px;
            border: 0;
            background: transparent;
            color: #222;
            text-decoration: underline;
            cursor: pointer;
        }

        .error {
            margin: 12px 0;
            color: #b42318;
            font-size: 13px;
        }

        .success {
            margin: 12px 0;
            color: #067647;
            font-size: 13px;
        }

        .hint {
            margin-top: 16px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>

<body>
    <main class="verification-card">
        <div class="brand">BiliHub</div>

        <h1>Verify Your Email</h1>

        <p class="description">
            We sent a 6-digit verification code to
            <span class="email">{{ $email }}</span>.
        </p>

        @if(session('success'))
        <p class="success" role="status">
            {{ session('success') }}
        </p>
        @endif

        @if($errors->any())
        <div class="error" role="alert">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route($verifyRoute ?? 'buyer.email.verify.submit') }}">
            @csrf

            <label for="otp">Verification Code</label>

            <input
                id="otp"
                name="otp"
                class="otp-input"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                autocomplete="one-time-code"
                placeholder="000000"
                required
                autofocus>

            <button type="submit" class="primary-button">
                Verify Email
            </button>
        </form>

        <form method="POST" action="{{ route($resendRoute ?? 'buyer.email.otp.resend') }}">
            @csrf

            <button type="submit" class="resend-button">
                Resend Verification Code
            </button>
        </form>

        <p class="hint">
            Your code expires in 5 minutes.
            {{ $approvalMessage ?? 'After verification, your account will still require administrator approval.' }}
        </p>
    </main>
</body>

</html>
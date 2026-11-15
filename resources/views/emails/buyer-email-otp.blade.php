
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiliHub Email Verification</title>
</head>
<body style="margin:0;padding:32px 16px;background:#f5f5f5;font-family:Arial,sans-serif;color:#222;">

    <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5e5e5;border-radius:12px;overflow:hidden;">

        <div style="background:#171717;padding:24px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:26px;">
                BiliHub
            </h1>
        </div>

        <div style="padding:32px 28px;text-align:center;">

            <h2 style="margin-top:0;font-size:22px;">
                Verify Your Email Address
            </h2>

            <p style="color:#666;line-height:1.6;">
                Thank you for registering with BiliHub!
                Use the verification code below to confirm
                that this email address belongs to you.
            </p>

            <div style="background:#f7f7f7;border:1px solid #e5e5e5;border-radius:10px;padding:20px;margin:28px 0;">
                <span style="font-size:36px;font-weight:bold;letter-spacing:8px;color:#171717;">
                    {{ $otp }}
                </span>
            </div>

            <p style="font-size:14px;color:#666;">
                This verification code expires in
                <strong>5 minutes</strong>.
            </p>

            <p style="font-size:14px;color:#666;line-height:1.6;">
                {{ $approvalMessage ?? 'After verifying your email, your account will remain pending until an administrator approves your registration.' }}
            </p>

            <p style="font-size:13px;color:#888;">
                If you did not register with BiliHub,
                you can safely ignore this email.
            </p>

        </div>

        <div style="background:#fafafa;padding:18px;text-align:center;border-top:1px solid #eee;">
            <p style="margin:0;font-size:12px;color:#999;">
                &copy; {{ date('Y') }} BiliHub. All rights reserved.
            </p>
        </div>

    </div>

</body>
</html>

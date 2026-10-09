<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeLoop Verification Code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8f9fc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111827;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fc; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 480px; background-color: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 32px 32px 24px; text-align: center;">
                            <div style="display: inline-block; width: 44px; height: 44px; line-height: 44px; border-radius: 12px; background-color: rgba(155, 138, 251, 0.15); color: #8874f9; font-weight: 700; font-size: 18px; margin-bottom: 16px;">
                                LL
                            </div>
                            <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #111827; letter-spacing: -0.02em;">
                                @if($purpose === 'password_reset')
                                    Password Reset Code
                                @else
                                    Verify Your Email
                                @endif
                            </h1>
                            <p style="margin: 8px 0 0; font-size: 13px; color: #6b7280; line-height: 1.5;">
                                @if($purpose === 'password_reset')
                                    Use the 6-digit code below to securely reset your LifeLoop password.
                                @else
                                    Thank you for joining LifeLoop. Enter this 6-digit code to complete verification.
                                @endif
                            </p>
                        </td>
                    </tr>

                    <!-- OTP Code Box -->
                    <tr>
                        <td align="center" style="padding: 0 32px 24px;">
                            <div style="background-color: #f3f5fa; border-radius: 12px; padding: 20px; text-align: center; border: 1px dashed #d1d5db;">
                                <span style="font-family: 'Courier New', Courier, monospace; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #7862f7; display: inline-block;">
                                    {{ $code }}
                                </span>
                            </div>
                            <p style="margin: 12px 0 0; font-size: 12px; color: #9ca3af; text-align: center;">
                                This code is valid for <strong>5 minutes</strong>. Do not share it with anyone.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer Notice -->
                    <tr>
                        <td style="padding: 24px 32px 32px; border-top: 1px solid #f3f4f6; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.5;">
                                If you did not request this email, no action is required and your account remains secure.
                            </p>
                            <p style="margin: 12px 0 0; font-size: 11px; color: #d1d5db;">
                                &copy; {{ date('Y') }} LifeLoop. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

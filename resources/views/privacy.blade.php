<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Privacy Policy — {{ config('app.name') }}</title>
<style>
  body { font-family: system-ui, sans-serif; background: #F8FAFC; color: #0F172A;
         margin: 0; padding: 24px 16px; line-height: 1.65; }
  main { max-width: 720px; margin: 0 auto; background: #FFFFFF;
         border: 1px solid #E4E7EB; border-radius: 16px; padding: 32px; }
  h1 { font-size: 1.5rem; } h2 { font-size: 1.05rem; margin-top: 1.6em; }
  p, li { color: #334155; font-size: .95rem; }
</style>
</head>
<body>
<main>
  <h1>{{ config('app.name') }} — Privacy Policy</h1>
  <p>{{ config('app.name') }} is a community map of Addis Ababa condominium
     blocks. You can browse and navigate without an account.</p>
  <h2>What we collect</h2>
  <ul>
    <li><strong>Account data</strong> (only if you sign up): email address,
        display name, and — if you use Telegram sign-in — the account
        identifier Telegram shares with us. Passwords are stored hashed.</li>
    <li><strong>Contributions</strong>: projects, blocks, corrections,
        confirmations and reports you submit, shown publicly on the map
        together with your display name on leaderboards.</li>
    <li><strong>Location</strong>: your device location is used only on your
        device to center the map and place pins. It is never uploaded or
        stored unless you submit it as a block/project coordinate.</li>
  </ul>
  <h2>What we don't do</h2>
  <ul>
    <li>No ads, no analytics SDKs, no sale or sharing of personal data.</li>
    <li>No background location tracking.</li>
  </ul>
  <h2>Deleting your account</h2>
  <p>In the app: Profile → Delete account. This permanently removes your
     account, sessions and votes. Map contributions remain, anonymised.</p>
  <h2>Contact</h2>
  <p>Questions or data requests:
     <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a></p>
</main>
</body>
</html>

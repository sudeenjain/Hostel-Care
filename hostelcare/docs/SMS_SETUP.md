# Free SMS setup (your own phone as the SMS sender)

HostelCare sends SMS through the free, open-source **SMS Gateway for Android** app. The app runs on an Android phone with a working SIM and sends ordinary SMS from it. You pay only what your SIM plan charges per SMS. No account is needed in local mode.

Project: https://github.com/capcom6/android-sms-gateway  Docs: https://docs.sms-gate.app

## What you need
- An Android phone with a SIM that can send SMS, kept charged, switched on and on Wi-Fi.
- The computer or server running HostelCare on the **same Wi-Fi network** (local mode), or any internet server (cloud mode, below).

## Steps (local mode)
1. Install the app on the phone (see the project page for the download).
2. In the app, switch on the **Local server**. The app shows the phone's address (for example `http://192.168.1.50:8080`) and a username and password. Note all three.
3. Tell HostelCare where the phone is, then start it. Windows PowerShell:

       $env:HC_SMS_ENABLED="1"
       $env:HC_SMS_URL="http://192.168.1.50:8080"
       $env:HC_SMS_USER="<username shown in the app>"
       $env:HC_SMS_PASS="<password shown in the app>"
       php -S 0.0.0.0:8000 -t public

   Linux or Mac: use `export HC_SMS_ENABLED=1` and so on.
4. Sign in as admin, open **SMS**, and press **Send a test to my number** (add your own mobile number under Profile first).
5. Students and staff tick "Send me SMS updates" when registering or in Profile.

If the test fails, the admin SMS page shows the reason. Common causes: wrong IP (phones change address; give it a fixed one in your router), phone and computer on different networks, wrong username or password, phone battery saver stopping the app.

## Cloud mode (computer and phone on different networks)
The same app can relay through the project's cloud server. Create the account in the app, then set `HC_SMS_URL=https://api.sms-gate.app` with the username and password from the app. Messages then travel through a third-party server, so prefer local mode for anything private. Check the project's current terms and limits.

## If the endpoint differs
Your app version may use a different path. Check the API page at https://docs.sms-gate.app and set `HC_SMS_PATH` (default `/3rdparty/v1/messages`).

## Limits and honesty
- Indian operators limit normal SIMs to roughly 100 SMS per day. HostelCare stops at `sms.daily_cap` (default 90) and sends the rest the next day.
- This suits one hostel and a project demo. A large deployment would use a commercial, DLT-registered gateway.
- Messages contain only the ticket number and status.
- The automated tests use a mock gateway and send nothing. Real delivery is proven only by your own test on a real phone.

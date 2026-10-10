# Build the Android APK on your Windows computer

This is the React Native/Expo application source, not an APK. Its backend URL is `https://chillbreeze.info/api/v1`. Deploy the Laravel backend using `hostinger.md` first so registration, login and quizzes have a working server.

## Install prerequisites

Install Node.js 24 LTS, Android Studio, Java JDK 21 and JDK 17. In Android Studio's SDK Manager install Android SDK Platform 36, Android SDK Build-Tools 35.0.0 and 36.0.0, Platform-Tools, NDK 27.1.12297006 and CMake 3.22.1 (enable Show Package Details to select versions). No Expo account is required for this local build.

Set Windows user environment variables through System Properties:

- `JAVA_HOME`: your JDK 21 installation directory.
- `ANDROID_HOME`: your Android SDK directory, usually `C:\Users\YOUR_NAME\AppData\Local\Android\Sdk`.
- Add `%JAVA_HOME%\bin` and `%ANDROID_HOME%\platform-tools` to your user `Path`.

Restart PowerShell and verify `node --version`, `java -version` and `adb version`. The React Native Gradle plugin also needs JDK 17. Create or edit `%USERPROFILE%\.gradle\gradle.properties`, preserving existing settings, and add its real installation directory using forward slashes:

```properties
org.gradle.java.installations.paths=C:/Program Files/Eclipse Adoptium/YOUR_JDK_17_DIRECTORY
```

Use a short extraction path, such as `C:\QuizApp`, to reduce Windows native build path problems. Android Studio can open the generated `mobile/android` project after the prebuild step.

## Configure and build

Extract `mobile-source.zip`. Open PowerShell in the extracted `mobile` folder:

```powershell
npm ci
Copy-Item .env.example .env
notepad .env
```

Confirm these public settings:

```dotenv
EXPO_PUBLIC_API_URL=https://chillbreeze.info/api/v1
EXPO_PUBLIC_POLICY_URL=https://chillbreeze.info/privacy
```

Publish your reviewed policy at that URL. Do not add database credentials, APP_KEY or administrator passwords to the app. The API address is embedded in the APK; rebuild if you change it.

```powershell
npx tsc --noEmit
npx expo prebuild --platform android --no-install
Set-Content -Path android/local.properties -Value ("sdk.dir=" + $env:ANDROID_HOME.Replace('\','/')) -Encoding ascii
cd android
.\gradlew.bat --no-daemon --max-workers=2 '-PreactNativeArchitectures=arm64-v8a' assembleRelease
```

The first build downloads Gradle and Android dependencies and can take several minutes. Allow HTTPS access to Maven Central, Google Android repositories, Gradle repositories and `repo.reactnative.dev`. Our cloud build was blocked specifically by its network policy for that last domain; this does not mean your computer has the same restriction. Keep TLS and artifact verification enabled.

After `BUILD SUCCESSFUL`, the standalone APK is at:

```
mobile\android\app\build\outputs\apk\release\app-release.apk
```

This ARM64 build targets common modern Android phones running Android 7.0 or later. To build all default CPU architectures, omit the `-PreactNativeArchitectures=arm64-v8a` argument; expect greater disk and build-time requirements.

## Install and verify

Copy the APK to your Android phone and open it, allowing installation from that source when Android asks. Alternatively enable USB debugging and run:

```powershell
adb install -r app\build\outputs\apk\release\app-release.apk
```

This release variant includes its JavaScript bundle and does not need Metro. Check registration, real verification-email delivery, login, today's questions, one-time rewards and logout. Declining optional permissions must still allow quizzes. Test explicitly selected contact/location sharing only if enabled in the backend.

## Signing and public distribution

The generated Expo Android template signs this local release variant with its **test/debug key**. It is suitable for installation testing, not public distribution. Before publishing, create an Android upload key you control through Android Studio's Generate Signed Bundle / APK flow, configure release signing securely, and back up the key and passwords privately. Never commit signing passwords or private keys. Keep the same signing key for updates.

A differently signed production APK cannot update a test-key installation with the same package ID: uninstall the test app first. The configured ID is `info.chillbreeze.learnandearn`; confirm it before public distribution. Google Play normally uses an AAB, not an APK. Complete the remaining checks and dependency fixes in `release-status.md` before public release.

For an optional Expo cloud build instead, follow the EAS preview commands in `hostinger.md`; that route requires an Expo account and produces an APK download after the build completes.

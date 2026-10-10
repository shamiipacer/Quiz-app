#!/usr/bin/env bash
set -euo pipefail
repo_dir=$(cd "$(dirname "$0")/.." && pwd)
: "${ANDROID_HOME:?Set ANDROID_HOME to an installed Android SDK}"
export EXPO_NO_TELEMETRY=1
cd "$repo_dir/mobile"
# Configure mobile/.env with your public HTTPS API and policy URLs first.
npm ci
npx expo prebuild --platform android --no-install
printf 'sdk.dir=%s\n' "$ANDROID_HOME" > android/local.properties
cd android
# The Expo template uses its debug signing key for this local test artifact.
# EAS preview builds use a separate managed signing key for distribution.
./gradlew --no-daemon --max-workers=2 -PreactNativeArchitectures=arm64-v8a assembleRelease
mkdir -p "$repo_dir/dist"
cp app/build/outputs/apk/release/app-release.apk "$repo_dir/dist/learn-and-earn-test.apk"
sha256sum "$repo_dir/dist/learn-and-earn-test.apk"

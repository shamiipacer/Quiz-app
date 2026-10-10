import React, {
  useState,
  useEffect,
  createContext,
  useContext,
  useCallback,
} from "react";
import {
  ActivityIndicator,
  Image,
  Alert,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
  useColorScheme,
} from "react-native";
import {
  NavigationContainer,
  DefaultTheme,
  DarkTheme,
  useFocusEffect,
} from "@react-navigation/native";
import {
  createNativeStackNavigator,
  NativeStackScreenProps,
} from "@react-navigation/native-stack";
import { SafeAreaProvider, SafeAreaView } from "react-native-safe-area-context";
import * as Contacts from "expo-contacts/legacy";
import * as Location from "expo-location";
import * as Notifications from "expo-notifications";
import {
  api,
  imageUrl,
  Assignment,
  Category,
  restoreToken,
  setToken,
} from "./src/api";
type Routes = {
  Home: undefined;
  Quiz: { assignment: Assignment };
  History: undefined;
  Settings: undefined;
  Sharing: undefined;
  Notifications: undefined;
};
const Stack = createNativeStackNavigator<Routes>();
const Auth = createContext({
  signedIn: false,
  setSignedIn: (_: boolean) => {},
});
const Colors = createContext({
  bg: "#F5F6FB",
  card: "#FFFFFF",
  text: "#172543",
  muted: "#65718B",
  border: "#DFE5F0",
});
function Button({
  title,
  onPress,
  disabled = false,
}: {
  title: string;
  onPress: () => void;
  disabled?: boolean;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={title}
      disabled={disabled}
      onPress={onPress}
      style={[styles.button, disabled && { opacity: 0.5 }]}
    >
      <Text style={styles.buttonText}>{title}</Text>
    </Pressable>
  );
}
function Page({ children }: { children: React.ReactNode }) {
  const c = useContext(Colors);
  return (
    <SafeAreaView edges={["bottom"]} style={{ flex: 1, backgroundColor: c.bg }}>
      <ScrollView contentContainerStyle={styles.page}>{children}</ScrollView>
    </SafeAreaView>
  );
}
function Card({ children }: { children: React.ReactNode }) {
  const c = useContext(Colors);
  return (
    <View style={[styles.card, { backgroundColor: c.card }]}>{children}</View>
  );
}
function Heading({ children }: { children: React.ReactNode }) {
  const c = useContext(Colors);
  return <Text style={[styles.heading, { color: c.text }]}>{children}</Text>;
}
function Copy({ children }: { children: React.ReactNode }) {
  const c = useContext(Colors);
  return <Text style={[styles.copy, { color: c.muted }]}>{children}</Text>;
}
function Field({
  label,
  value,
  onChange,
  secure = false,
}: {
  label: string;
  value: string;
  onChange: (s: string) => void;
  secure?: boolean;
}) {
  const c = useContext(Colors);
  return (
    <View style={{ marginVertical: 8 }}>
      <Copy>{label}</Copy>
      <TextInput
        accessibilityLabel={label}
        value={value}
        onChangeText={onChange}
        secureTextEntry={secure}
        autoCapitalize="none"
        style={[
          styles.input,
          { color: c.text, borderColor: c.border, backgroundColor: c.card },
        ]}
      />
    </View>
  );
}
function SignIn() {
  const { setSignedIn } = useContext(Auth);
  const [mode, setMode] = useState<"login" | "register" | "forgot" | "reset">(
    "login",
  );
  const [name, setName] = useState(""),
    [email, setEmail] = useState(""),
    [password, setPassword] = useState(""),
    [confirmation, setConfirmation] = useState(""),
    [reset, setReset] = useState("");
  const [terms, setTerms] = useState(false),
    [privacy, setPrivacy] = useState(false),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  async function submit() {
    setBusy(true);
    setError("");
    try {
      if (mode === "forgot") {
        await api("password/forgot", "POST", { email });
        Alert.alert(
          "Check your email",
          "If the account exists, a reset link was sent.",
        );
        setMode("reset");
      } else if (mode === "reset") {
        await api("password/reset", "POST", {
          email,
          token: reset,
          password,
          password_confirmation: confirmation,
        });
        setMode("login");
      } else {
        const result = await api(mode, "POST", {
          name,
          email,
          password,
          password_confirmation: confirmation,
          terms,
          privacy,
          timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        });
        await setToken(result.token);
        setSignedIn(true);
      }
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Page>
      <Text style={styles.brand}>✦ LEARN & EARN</Text>
      <Heading>A little learning. Every day.</Heading>
      <Copy>
        Explore ten subjects, build a daily habit and earn virtual coins.
      </Copy>
      <Card>
        <Heading>
          {mode === "register"
            ? "Create your account"
            : mode === "login"
              ? "Welcome back"
              : mode === "forgot"
                ? "Reset your password"
                : "Choose a new password"}
        </Heading>
        {mode === "register" && (
          <Field label="Full name" value={name} onChange={setName} />
        )}
        <Field label="Email address" value={email} onChange={setEmail} />
        {mode !== "forgot" && (
          <Field
            label="Password (12+ characters with letters and numbers)"
            value={password}
            onChange={setPassword}
            secure
          />
        )}
        {(mode === "register" || mode === "reset") && (
          <Field
            label="Confirm password"
            value={confirmation}
            onChange={setConfirmation}
            secure
          />
        )}
        {mode === "reset" && (
          <Field
            label="Reset token from email"
            value={reset}
            onChange={setReset}
          />
        )}
        {mode === "register" && (
          <>
            <View style={styles.row}>
              <Switch value={terms} onValueChange={setTerms} />
              <Copy>I accept the Terms and Conditions</Copy>
            </View>
            <View style={styles.row}>
              <Switch value={privacy} onValueChange={setPrivacy} />
              <Copy>I accept the Privacy Policy</Copy>
            </View>
            <Button
              title="Read terms and privacy"
              onPress={() => {
                const url = process.env.EXPO_PUBLIC_POLICY_URL;
                if (url) Linking.openURL(url);
                else
                  Alert.alert(
                    "Not configured",
                    "Policy documents must be published before public registration.",
                  );
              }}
            />
          </>
        )}
        {!!error && (
          <Text accessibilityRole="alert" style={styles.error}>
            {error}
          </Text>
        )}
        <Button
          title={
            busy
              ? "Please wait…"
              : mode === "login"
                ? "Sign in"
                : mode === "register"
                  ? "Create account"
                  : "Continue"
          }
          onPress={submit}
          disabled={busy || (mode === "register" && (!terms || !privacy))}
        />
        <Button
          title={mode === "login" ? "Create an account" : "Back to sign in"}
          onPress={() => setMode(mode === "login" ? "register" : "login")}
        />
        {mode === "login" && (
          <Button title="Forgot password?" onPress={() => setMode("forgot")} />
        )}
      </Card>
      <Copy>
        No contacts or location permission is required to use quizzes. Coins are
        virtual points with no cash value.
      </Copy>
    </Page>
  );
}
function Home({ navigation }: NativeStackScreenProps<Routes, "Home">) {
  const [user, setUser] = useState<any>(),
    [balance, setBalance] = useState(0),
    [categories, setCategories] = useState<Category[]>([]),
    [questions, setQuestions] = useState<Assignment[]>([]),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(true);
  const load = useCallback(async () => {
    setBusy(true);
    setError("");
    try {
      const [u, b, c] = await Promise.all([
        api("profile"),
        api("coins/balance"),
        api<Category[]>("categories"),
      ]);
      setUser(u);
      setBalance(b.balance);
      setCategories(c);
      if (u.email_verified_at) {
        const q = await api<{ data: Assignment[] }>("questions/today");
        setQuestions(q.data);
      }
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }, []);
  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );
  const done = questions.filter((q) => q.result).length;
  return (
    <Page>
      <Text style={styles.brand}>YOUR DAILY DISCOVERY</Text>
      <View style={styles.row}>
        <View style={styles.avatar}>
          <Text style={{ fontSize: 24, fontWeight: "800", color: "#5260D9" }}>
            {user?.name?.slice(0, 1).toUpperCase() ?? "L"}
          </Text>
        </View>
        <Heading>Hello, {user?.name?.split(" ")[0] ?? "learner"} 👋</Heading>
      </View>
      <Copy>Small steps today. A brighter mind tomorrow.</Copy>
      <Card>
        <View style={styles.row}>
          <View style={{ flex: 1 }}>
            <Copy>Your virtual coins</Copy>
            <Heading>◉ {balance.toLocaleString()}</Heading>
          </View>
          <Copy>
            {done} / {questions.length} completed · {questions.length - done}{" "}
            remaining
          </Copy>
        </View>
        <View style={styles.track}>
          <View
            style={[
              styles.progress,
              {
                width: `${questions.length ? (done / questions.length) * 100 : 0}%`,
              },
            ]}
          />
        </View>
      </Card>
      {busy && <ActivityIndicator />}
      {!!error && <Text style={styles.error}>{error}</Text>}
      {user && !user.email_verified_at && (
        <Card>
          <Heading>Verify your email</Heading>
          <Copy>
            Open your verification email, then refresh to unlock daily
            questions.
          </Copy>
          <Button title="Refresh" onPress={load} />
          <Button
            title="Resend verification email"
            onPress={() =>
              api("email/verification-notification", "POST")
                .then(() => Alert.alert("Requested", "Check your email."))
                .catch((e) => setError(e.message))
            }
          />
        </Card>
      )}
      <Heading>Explore your subjects</Heading>
      {categories.map((c, i) => {
        const qs = questions.filter((q) => q.question.category_id === c.id),
          complete = qs.filter((q) => q.result).length;
        return (
          <Card key={c.id}>
            <View style={styles.row}>
              <Text style={styles.subjectIcon}>
                {c.icon && c.icon !== "book"
                  ? c.icon
                  : ["📖", "⚡", "🌱", "∑", "🧪", "💻", "💡", "🏛", "🌍", "📚"][
                      i % 10
                    ]}
              </Text>
              <View>
                <Heading>{c.name}</Heading>
                <Copy>
                  {complete} of {qs.length} completed
                </Copy>
              </View>
            </View>
            {qs
              .filter((q) => !q.result)
              .map((q, j) => (
                <Button
                  key={q.id}
                  title={`Start question ${j + 1}`}
                  onPress={() => navigation.navigate("Quiz", { assignment: q })}
                />
              ))}
            {qs.length === 0 && <Copy>No questions available yet.</Copy>}
          </Card>
        );
      })}
      <Button
        title="Rewards & answer history"
        onPress={() => navigation.navigate("History")}
      />
      <Button
        title="Notifications"
        onPress={() => navigation.navigate("Notifications")}
      />
      <Button
        title="Account & privacy"
        onPress={() => navigation.navigate("Settings")}
      />
    </Page>
  );
}
function Quiz({ route, navigation }: NativeStackScreenProps<Routes, "Quiz">) {
  const q = route.params.assignment;
  const c = useContext(Colors);
  const [answer, setAnswer] = useState(""),
    [result, setResult] = useState<any>(),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  async function submit() {
    setBusy(true);
    setError("");
    try {
      setResult(await api(`questions/${q.id}/answer`, "POST", { answer }));
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Page>
      <Text style={styles.brand}>DAILY PRACTICE</Text>
      <Card>
        <Heading>{q.question.text}</Heading>
        {q.question.image_path && (
          <Image
            source={{ uri: imageUrl(q.question.image_path) }}
            accessibilityLabel="Question illustration"
            resizeMode="contain"
            style={{ width: "100%", height: 220 }}
          />
        )}
        {q.question.type === "single_answer" ? (
          <Field label="Your answer" value={answer} onChange={setAnswer} />
        ) : (
          q.question.options.map((o) => (
            <Pressable
              key={o.id}
              accessibilityRole="radio"
              accessibilityState={{ selected: answer === String(o.id) }}
              disabled={!!result || busy}
              onPress={() => setAnswer(String(o.id))}
              style={[
                styles.option,
                { borderColor: answer === String(o.id) ? "#5966E8" : c.border },
              ]}
            >
              <Text style={{ color: c.text, fontSize: 17 }}>{o.text}</Text>
            </Pressable>
          ))
        )}
        {!!error && <Text style={styles.error}>{error}</Text>}
        {!result ? (
          <Button
            title={busy ? "Checking…" : "Submit answer"}
            disabled={!answer || busy}
            onPress={submit}
          />
        ) : (
          <>
            <Heading>
              {result.correct ? "Great work! ✨" : "Keep learning 🌱"}
            </Heading>
            <Copy>{result.explanation}</Copy>
            <Copy>
              +{result.coins_awarded} virtual coins · Balance {result.balance}
            </Copy>
            <Button title="Back to today" onPress={() => navigation.goBack()} />
          </>
        )}
      </Card>
    </Page>
  );
}
function History() {
  const [rows, setRows] = useState<any[]>([]),
    [questions, setQuestions] = useState<Assignment[]>([]),
    [error, setError] = useState("");
  useEffect(() => {
    Promise.all([api("coins/transactions"), api("answers")])
      .then(([r, q]) => {
        setRows(r.data);
        setQuestions(q.data);
      })
      .catch((e) => setError(e.message));
  }, []);
  return (
    <Page>
      <Heading>Your progress</Heading>
      {!!error && <Text style={styles.error}>{error}</Text>}
      {questions
        .filter((q) => q.result)
        .map((q) => (
          <Card key={q.id}>
            <Copy>{q.question.text}</Copy>
            <Copy>
              {q.result?.correct ? "Correct" : "Incorrect"} · +
              {q.result?.coins_awarded} coins
            </Copy>
          </Card>
        ))}
      <Heading>Recent coin transactions</Heading>
      {rows.map((r) => (
        <Card key={r.id}>
          <Heading>
            {r.amount > 0 ? "+" : ""}
            {r.amount} coins
          </Heading>
          <Copy>
            {r.reason} · {r.created_at}
          </Copy>
        </Card>
      ))}
      {!rows.length && <Copy>No rewards recorded yet.</Copy>}
    </Page>
  );
}
function Settings({ navigation }: NativeStackScreenProps<Routes, "Settings">) {
  const { setSignedIn } = useContext(Auth);
  const [name, setName] = useState(""),
    [timezone, setTimezone] = useState("UTC"),
    [schedule, setSchedule] = useState("00:00"),
    [error, setError] = useState("");
  useEffect(() => {
    api("profile")
      .then((u) => {
        setName(u.name);
        setTimezone(u.timezone);
        setSchedule(u.daily_schedule.slice(0, 5));
      })
      .catch((e) => setError(e.message));
  }, []);
  async function run(action: () => Promise<unknown>) {
    try {
      await action();
      Alert.alert("Done", "Your request was saved.");
    } catch (e) {
      setError((e as Error).message);
    }
  }
  return (
    <Page>
      <Heading>Account & privacy</Heading>
      <Card>
        <Field label="Full name" value={name} onChange={setName} />
        <Button
          title="Save profile"
          onPress={() => run(() => api("profile", "PATCH", { name }))}
        />
        <Field
          label="Timezone (IANA, e.g. Asia/Karachi)"
          value={timezone}
          onChange={setTimezone}
        />
        <Field
          label="Daily start time (HH:MM)"
          value={schedule}
          onChange={setSchedule}
        />
        <Copy>
          Schedule changes take effect after at least 24 hours and after the
          current period ends.
        </Copy>
        <Button
          title="Request schedule change"
          onPress={() =>
            run(() =>
              api("profile", "PATCH", { timezone, daily_schedule: schedule }),
            )
          }
        />
      </Card>
      <Button
        title="Optional sharing"
        onPress={() => navigation.navigate("Sharing")}
      />
      <Button
        title="Request account deletion"
        onPress={() =>
          Alert.alert(
            "Request account deletion?",
            "Your request will be reviewed under the retention policy.",
            [
              { text: "Cancel", style: "cancel" },
              {
                text: "Request deletion",
                style: "destructive",
                onPress: () =>
                  run(() => api("account/deletion-request", "POST")),
              },
            ],
          )
        }
      />
      <Button
        title="Sign out"
        onPress={() =>
          run(async () => {
            try {
              await api("logout", "POST");
            } finally {
              await setToken(null);
              setSignedIn(false);
            }
          })
        }
      />
      {!!error && <Text style={styles.error}>{error}</Text>}
    </Page>
  );
}
function NotificationScreen() {
  const [rows, setRows] = useState<any[]>([]),
    [settings, setSettings] = useState<any>(),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false);
  useEffect(() => {
    Promise.all([api("notifications"), api("notifications/settings")])
      .then(([r, s]) => {
        setRows(r.data);
        setSettings(s);
      })
      .catch((e) => setError(e.message));
  }, []);
  async function toggle() {
    setBusy(true);
    try {
      if (settings.enabled) {
        await api("notifications/preferences", "PATCH", { enabled: false });
        setSettings({ ...settings, enabled: false });
      } else {
        if (!settings.push_enabled || !settings.project_id)
          throw new Error(
            "Push delivery is not configured. In-app announcements remain available.",
          );
        if (Platform.OS === "android")
          await Notifications.setNotificationChannelAsync("default", {
            name: "Learning reminders",
            importance: Notifications.AndroidImportance.DEFAULT,
          });
        const p = await Notifications.requestPermissionsAsync();
        if (p.status !== "granted")
          throw new Error("Permission declined. Quizzes remain available.");
        const token = await Notifications.getExpoPushTokenAsync({
          projectId: settings.project_id,
        });
        await api("notifications/preferences", "PATCH", {
          enabled: true,
          push_token: token.data,
        });
        setSettings({ ...settings, enabled: true });
      }
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Page>
      <Heading>Learning updates</Heading>
      <Copy>
        Optional push notifications provide learning reminders and
        announcements. Enable them only if you want device alerts. You can
        switch them off any time.
      </Copy>
      <Button
        title={
          settings?.enabled
            ? "Turn off push alerts"
            : "Choose to enable push alerts"
        }
        disabled={busy}
        onPress={toggle}
      />
      {!!error && <Text style={styles.error}>{error}</Text>}
      {rows.map((row) => {
        const n = JSON.parse(row.data);
        return (
          <Card key={row.id}>
            <Heading>{n.title}</Heading>
            <Copy>{n.body}</Copy>
            <Copy>{row.created_at}</Copy>
            {!row.read_at && (
              <Button
                title="Mark read"
                onPress={() =>
                  api(`notifications/${row.id}/read`, "PATCH")
                    .then(() =>
                      setRows(
                        rows.map((r) =>
                          r.id === row.id
                            ? { ...r, read_at: new Date().toISOString() }
                            : r,
                        ),
                      ),
                    )
                    .catch((e) => setError(e.message))
                }
              />
            )}
          </Card>
        );
      })}
      {!rows.length && <Copy>No announcements yet.</Copy>}
    </Page>
  );
}
function Sharing() {
  const [config, setConfig] = useState<any>(),
    [contact, setContact] = useState<{ name: string; phone: string } | null>(
      null,
    ),
    [location, setLocation] = useState<{
      latitude: number;
      longitude: number;
    } | null>(null),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false);
  useEffect(() => {
    api("sharing/settings")
      .then(setConfig)
      .catch((e) => setError(e.message));
  }, []);
  async function run(action: () => Promise<void>) {
    setBusy(true);
    setError("");
    try {
      await action();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  }
  async function selectContact() {
    if (Platform.OS === "android") {
      const permission = await Contacts.requestPermissionsAsync();
      if (!permission.granted)
        throw new Error("Permission declined. Quizzes remain available.");
    }
    const chosen = await Contacts.presentContactPickerAsync();
    if (chosen) {
      const phone = chosen.phoneNumbers?.[0]?.number;
      if (!phone) throw new Error("Choose a contact with a phone number.");
      setContact({ name: chosen.name, phone });
    }
  }
  async function selectLocation() {
    const p = await Location.requestForegroundPermissionsAsync();
    if (!p.granted)
      throw new Error("Permission declined. Quizzes remain available.");
    const l = await Location.getCurrentPositionAsync({
      accuracy: Location.Accuracy.Low,
    });
    setLocation({
      latitude: Math.round(l.coords.latitude * 100) / 100,
      longitude: Math.round(l.coords.longitude * 100) / 100,
    });
  }
  async function share(purpose: "contacts" | "location") {
    await api("consents", "POST", {
      purpose,
      policy_version: config.policy_version,
      authorized_fields:
        purpose === "contacts" ? ["name", "phone"] : ["latitude", "longitude"],
      granted: true,
    });
    await api(
      `${purpose === "contacts" ? "contacts" : "location"}/share`,
      "POST",
      purpose === "contacts" ? { contacts: [contact] } : location,
    );
    setContact(null);
    setLocation(null);
    Alert.alert("Shared", "Only the confirmed information was uploaded.");
  }
  return (
    <Page>
      <Heading>Sharing is your choice</Heading>
      <Copy>
        You can decline and continue using every educational feature. Device
        access does not upload information. Review it here and explicitly
        confirm transmission.
      </Copy>
      {config?.enabled ? (
        <>
          <Card>
            <Heading>Purpose & retention</Heading>
            <Copy>{config.purpose}</Copy>
            <Copy>
              Stored for up to {config.retention_days} days. Authorized privacy
              administrators may access it. No marketing or messaging is
              authorized.
            </Copy>
          </Card>
          <Card>
            <Heading>Selected contact</Heading>
            <Copy>
              Choose one contact. Only the displayed name and first phone number
              will be sent after you confirm.
            </Copy>
            <Button
              title="Choose a contact"
              disabled={busy}
              onPress={() => run(selectContact)}
            />
            {contact && (
              <>
                <Copy>
                  {contact.name} · {contact.phone}
                </Copy>
                <Button
                  title="I consent: upload this contact"
                  disabled={busy}
                  onPress={() => run(() => share("contacts"))}
                />
                <Button
                  title="Discard selection"
                  onPress={() => setContact(null)}
                />
              </>
            )}
          </Card>
          <Card>
            <Heading>Approximate location</Heading>
            <Copy>
              A single foreground reading will be rounded to roughly kilometre
              precision. We never request background location.
            </Copy>
            <Button
              title="Choose to read location"
              disabled={busy}
              onPress={() => run(selectLocation)}
            />
            {location && (
              <>
                <Copy>
                  {location.latitude}, {location.longitude}
                </Copy>
                <Button
                  title="I consent: upload this location"
                  disabled={busy}
                  onPress={() => run(() => share("location"))}
                />
                <Button
                  title="Discard reading"
                  onPress={() => setLocation(null)}
                />
              </>
            )}
          </Card>
        </>
      ) : (
        <Card>
          <Copy>
            Optional sharing is currently disabled. No device permissions will
            be requested.
          </Copy>
        </Card>
      )}
      <Button
        title="Revoke consent & delete shared contacts"
        disabled={busy}
        onPress={() =>
          run(async () => {
            await api("contacts", "DELETE");
            setContact(null);
            Alert.alert(
              "Deleted",
              "Consent revoked and stored contacts deleted.",
            );
          })
        }
      />
      <Button
        title="Revoke consent & delete shared location"
        disabled={busy}
        onPress={() =>
          run(async () => {
            await api("location", "DELETE");
            setLocation(null);
            Alert.alert(
              "Deleted",
              "Consent revoked and stored location deleted.",
            );
          })
        }
      />
      <Button
        title="Manage device permissions"
        onPress={() => Linking.openSettings()}
      />
      {!!error && <Text style={styles.error}>{error}</Text>}
    </Page>
  );
}
export default function App() {
  const [signedIn, setSignedIn] = useState(false),
    [ready, setReady] = useState(false);
  const dark = useColorScheme() === "dark";
  useEffect(() => {
    restoreToken()
      .then(setSignedIn)
      .catch(() => {})
      .finally(() => setReady(true));
  }, []);
  const colors = dark
    ? {
        bg: "#10182B",
        card: "#1D2940",
        text: "#F2F5FF",
        muted: "#AFBBD2",
        border: "#35445E",
      }
    : {
        bg: "#F5F6FB",
        card: "#FFFFFF",
        text: "#172543",
        muted: "#65718B",
        border: "#DFE5F0",
      };
  return (
    <SafeAreaProvider>
      <Colors.Provider value={colors}>
        <Auth.Provider value={{ signedIn, setSignedIn }}>
          {!ready ? (
            <ActivityIndicator style={{ flex: 1 }} />
          ) : (
            <NavigationContainer theme={dark ? DarkTheme : DefaultTheme}>
              {signedIn ? (
                <Stack.Navigator
                  screenOptions={{ headerTitleStyle: { fontWeight: "700" } }}
                >
                  <Stack.Screen
                    name="Home"
                    component={Home}
                    options={{ title: "Learn & Earn" }}
                  />
                  <Stack.Screen
                    name="Quiz"
                    component={Quiz}
                    options={{ title: "Daily question" }}
                  />
                  <Stack.Screen name="History" component={History} />
                  <Stack.Screen name="Settings" component={Settings} />
                  <Stack.Screen name="Sharing" component={Sharing} />
                  <Stack.Screen
                    name="Notifications"
                    component={NotificationScreen}
                    options={{ title: "Notifications" }}
                  />
                </Stack.Navigator>
              ) : (
                <SignIn />
              )}
            </NavigationContainer>
          )}
        </Auth.Provider>
      </Colors.Provider>
    </SafeAreaProvider>
  );
}
const styles = StyleSheet.create({
  page: { padding: 22, gap: 14, paddingBottom: 40 },
  brand: {
    color: "#6972DD",
    fontWeight: "800",
    letterSpacing: 2,
    fontSize: 12,
    marginTop: 12,
  },
  heading: { fontSize: 26, fontWeight: "800", lineHeight: 34 },
  copy: { fontSize: 15, lineHeight: 24, flexShrink: 1 },
  card: { padding: 22, borderRadius: 22, gap: 14 },
  button: {
    backgroundColor: "#5260D9",
    padding: 15,
    borderRadius: 12,
    alignItems: "center",
    marginVertical: 4,
  },
  buttonText: { color: "white", fontSize: 15, fontWeight: "700" },
  input: {
    borderWidth: 1,
    borderRadius: 10,
    padding: 13,
    fontSize: 16,
    marginTop: 6,
  },
  row: { flexDirection: "row", alignItems: "center", gap: 14 },
  track: {
    height: 8,
    backgroundColor: "#E2E6F5",
    borderRadius: 8,
    overflow: "hidden",
  },
  progress: { height: 8, backgroundColor: "#8F79ED" },
  avatar: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: "#E4E7FF",
    alignItems: "center",
    justifyContent: "center",
  },
  subjectIcon: { fontSize: 30 },
  option: { padding: 18, borderWidth: 2, borderRadius: 12 },
  error: { color: "#D14960", lineHeight: 22 },
});

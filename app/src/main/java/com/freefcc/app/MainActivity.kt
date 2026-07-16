package com.freefcc.app

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.media.MediaPlayer
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.widget.VideoView
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.viewModels
import androidx.core.content.ContextCompat
import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.*
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.unit.sp
import androidx.compose.ui.res.painterResource
import androidx.compose.foundation.Image
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlin.math.sin
import kotlin.math.cos
import kotlin.math.PI

// ═══════════════════════════════════════════════════════════════════════
// Colors
// ═══════════════════════════════════════════════════════════════════════

private val BgDark = Color(0xFF0A0F1E)
private val BgMid = Color(0xFF101835)
private val BgLight = Color(0xFF182248)
private val CardBg = Color(0xFF131B3A)
private val CardBorder = Color(0xFF2A3566)
private val Cyan = Color(0xFF00E5FF)
private val Green = Color(0xFF00E676)
private val Amber = Color(0xFFFFAB00)
private val Red = Color(0xFFFF5252)
private val Purple = Color(0xFFBB86FC)
private val Pink = Color(0xFFFF4081)
private val TextWhite = Color(0xFFF5F7FF)
private val TextGray = Color(0xFF8E99C0)
private val TextDim = Color(0xFF5A6694)

private val BottomNavHeight = 72.dp

// ═══════════════════════════════════════════════════════════════════════
// Activity
// ═══════════════════════════════════════════════════════════════════════

class MainActivity : ComponentActivity() {

    private val viewModel: FccViewModel by viewModels()

    private val notificationPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { /* granted or denied — no further action needed */ }

    private val locationPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { /* optional — telemetry uses device GPS when granted */ }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        NotificationHelper.createChannel(this)
        requestNotificationPermission()
        requestLocationPermission()
        NotificationWorker.schedule(this)

        viewModel.init()

        setContent {
            MaterialTheme(
                colorScheme = darkColorScheme(
                    primary = Cyan, onPrimary = BgDark,
                    background = BgDark, onBackground = TextWhite,
                    surface = CardBg, onSurface = TextWhite,
                    error = Red, secondary = Green, tertiary = Amber
                )
            ) {
                AppRoot(viewModel)
            }
        }
    }

    override fun onStart() {
        super.onStart()
        val token = AuthManager.getToken(this) ?: return
        Thread { AuthApi.heartbeat(token) }.start()
    }

    override fun onResume() {
        super.onResume()
        viewModel.onAppResumed()
    }

    override fun onStop() {
        super.onStop()
        val token = AuthManager.getToken(this)
        Thread {
            try {
                kotlinx.coroutines.runBlocking {
                    TelemetryCollector.flushFeatureEvents(this@MainActivity)
                }
            } catch (_: Exception) {}
            if (token != null) AuthApi.goOffline(token)
        }.start()
    }

    private fun requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
            ) {
                notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
    }

    private fun requestLocationPermission() {
        val needFine = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION)
            != PackageManager.PERMISSION_GRANTED
        val needCoarse = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION)
            != PackageManager.PERMISSION_GRANTED
        if (needFine || needCoarse) {
            locationPermissionLauncher.launch(
                arrayOf(
                    Manifest.permission.ACCESS_FINE_LOCATION,
                    Manifest.permission.ACCESS_COARSE_LOCATION
                )
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Root layout
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun SplashScreen(onFinished: () -> Unit) {
    val phase = remember { Animatable(0f) }

    LaunchedEffect(Unit) {
        phase.animateTo(1f, tween(2800, easing = LinearEasing))
        delay(200)
        onFinished()
    }

    val p = phase.value

    val logoAlpha = (p / 0.15f).coerceIn(0f, 1f)
    val logoScale = if (p < 0.15f) {
        0.4f + 0.6f * EaseOutBack.transform(p / 0.15f)
    } else 1f

    val ringReveal = ((p - 0.1f) / 0.25f).coerceIn(0f, 1f)

    val titleAlpha = ((p - 0.25f) / 0.15f).coerceIn(0f, 1f)
    val titleOffsetY = if (p < 0.4f) {
        30f * (1f - EaseOutCubic.transform(((p - 0.25f) / 0.15f).coerceIn(0f, 1f)))
    } else 0f

    val lineWidth = ((p - 0.35f) / 0.15f).coerceIn(0f, 1f)

    val subtitleAlpha = ((p - 0.45f) / 0.12f).coerceIn(0f, 1f)

    val creditAlpha = ((p - 0.55f) / 0.15f).coerceIn(0f, 1f)
    val creditScale = if (p < 0.7f) {
        0.7f + 0.3f * EaseOutCubic.transform(((p - 0.55f) / 0.15f).coerceIn(0f, 1f))
    } else 1f

    val exitProgress = ((p - 0.88f) / 0.12f).coerceIn(0f, 1f)
    val exitAlpha = 1f - EaseInCubic.transform(exitProgress)
    val exitScale = 1f + 0.08f * EaseInCubic.transform(exitProgress)

    val inf = rememberInfiniteTransition(label = "splashInf")
    val glowPulse by inf.animateFloat(
        0.08f, 0.22f,
        infiniteRepeatable(tween(1600, easing = EaseInOutSine), RepeatMode.Reverse),
        label = "glow"
    )
    val ringRotation by inf.animateFloat(
        0f, 360f,
        infiniteRepeatable(tween(5000, easing = LinearEasing), RepeatMode.Restart),
        label = "ringRot"
    )
    val ring2Rotation by inf.animateFloat(
        360f, 0f,
        infiniteRepeatable(tween(7000, easing = LinearEasing), RepeatMode.Restart),
        label = "ring2Rot"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    listOf(Color(0xFF0D1530), Color(0xFF070C1E), Color(0xFF040810))
                )
            )
            .alpha(exitAlpha)
            .scale(exitScale),
        contentAlignment = Alignment.Center
    ) {
        Box(
            Modifier.size(320.dp).background(
                Brush.radialGradient(
                    listOf(Cyan.copy(glowPulse * logoAlpha), Color.Transparent), radius = 450f
                )
            )
        )
        Box(
            Modifier.size(260.dp).background(
                Brush.radialGradient(
                    listOf(Purple.copy(glowPulse * 0.4f * logoAlpha), Color.Transparent), radius = 380f
                )
            )
        )

        Canvas(
            modifier = Modifier
                .size(190.dp)
                .rotate(ringRotation)
                .alpha(ringReveal)
        ) {
            val sweep = ringReveal * 280f
            drawArc(
                brush = Brush.sweepGradient(
                    listOf(Cyan.copy(0.85f), Purple.copy(0.6f), Cyan.copy(0.05f))
                ),
                startAngle = -90f, sweepAngle = sweep, useCenter = false,
                style = Stroke(width = 2.5.dp.toPx(), cap = StrokeCap.Round)
            )
        }

        Canvas(
            modifier = Modifier
                .size(155.dp)
                .rotate(ring2Rotation)
                .alpha(ringReveal * 0.7f)
        ) {
            val sweep = ringReveal * 220f
            drawArc(
                brush = Brush.sweepGradient(
                    listOf(Green.copy(0.6f), Cyan.copy(0.4f), Green.copy(0.05f))
                ),
                startAngle = 90f, sweepAngle = sweep, useCenter = false,
                style = Stroke(width = 1.8.dp.toPx(), cap = StrokeCap.Round)
            )
        }

        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Image(
                painter = painterResource(R.drawable.hg_logo),
                contentDescription = null,
                modifier = Modifier
                    .size(100.dp)
                    .scale(logoScale)
                    .alpha(logoAlpha)
            )

            Spacer(Modifier.height(24.dp))

            Text(
                "DJI FCC Mod",
                color = Color.White.copy(alpha = titleAlpha),
                fontSize = 34.sp,
                fontWeight = FontWeight.Black,
                letterSpacing = 2.sp,
                modifier = Modifier.offset(y = titleOffsetY.dp)
            )

            Spacer(Modifier.height(10.dp))

            Box(
                Modifier
                    .fillMaxWidth(0.35f * EaseOutCubic.transform(lineWidth))
                    .height(2.dp)
                    .background(
                        Brush.horizontalGradient(
                            listOf(Color.Transparent, Cyan.copy(lineWidth), Purple.copy(lineWidth), Color.Transparent)
                        )
                    )
            )

            Spacer(Modifier.height(12.dp))

            Text(
                "FREKANS KONTROL SİSTEMİ",
                color = TextGray.copy(alpha = subtitleAlpha),
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 3.sp
            )

            Spacer(Modifier.height(44.dp))

            Surface(
                color = Green.copy(0.10f * creditAlpha),
                shape = RoundedCornerShape(20.dp),
                border = BorderStroke(1.dp, Green.copy(0.25f * creditAlpha)),
                modifier = Modifier
                    .alpha(creditAlpha)
                    .scale(creditScale)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(horizontal = 18.dp, vertical = 9.dp)
                ) {
                    Icon(
                        Icons.Filled.Code,
                        contentDescription = null,
                        tint = Green,
                        modifier = Modifier.size(15.dp)
                    )
                    Spacer(Modifier.width(8.dp))
                    Text(
                        "HG Tarafından Yapılmıştır",
                        color = Green,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.8.sp
                    )
                }
            }
        }
    }
}

/** Returns true only if there is an active, validated internet-capable network. */
private fun hasInternetConnection(context: Context): Boolean {
    val cm = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager ?: return false
    val network = cm.activeNetwork ?: return false
    val capabilities = cm.getNetworkCapabilities(network) ?: return false
    return capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
        capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
}

// ═══════════════════════════════════════════════════════════════════════
// Auth
// ═══════════════════════════════════════════════════════════════════════

private sealed class AuthUiState {
    data object Checking : AuthUiState()
    data class LoggedOut(val error: String? = null) : AuthUiState()
    data class LoggedIn(val member: MemberInfo) : AuthUiState()
}

@Composable
private fun AuthCheckingScreen() {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(listOf(Color(0xFF050A1A), BgDark, BgMid, BgDark))
            ),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            CircularProgressIndicator(strokeWidth = 2.5.dp, color = Cyan, modifier = Modifier.size(40.dp))
            Spacer(Modifier.height(16.dp))
            BodyText("Oturum doğrulanıyor...", Cyan)
        }
    }
}

@Composable
private fun LoginScreen(
    isLoading: Boolean,
    errorMessage: String?,
    onLogin: (username: String, password: String) -> Unit
) {
    var username by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    val canSubmit = username.isNotBlank() && password.isNotBlank() && !isLoading

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(listOf(Color(0xFF050A1A), BgDark, BgMid, BgDark))
            )
    ) {
        Box(
            Modifier
                .fillMaxWidth()
                .height(320.dp)
                .align(Alignment.TopCenter)
                .background(
                    Brush.radialGradient(
                        listOf(Cyan.copy(0.1f), Purple.copy(0.04f), Color.Transparent),
                        center = Offset(0f, 0f),
                        radius = 700f
                    )
                )
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 28.dp)
                .imePadding(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(80.dp))
            Image(
                painter = painterResource(R.drawable.hg_logo),
                contentDescription = null,
                modifier = Modifier.size(72.dp)
            )
            Spacer(Modifier.height(20.dp))
            Text("Giriş Yap", color = TextWhite, fontSize = 26.sp, fontWeight = FontWeight.Black)
            Spacer(Modifier.height(6.dp))
            BodyText("Devam etmek için hesabınızla giriş yapın.", TextGray)
            Spacer(Modifier.height(36.dp))

            OutlinedTextField(
                value = username,
                onValueChange = { username = it },
                label = { Text("Kullanıcı Adı") },
                singleLine = true,
                enabled = !isLoading,
                colors = loginFieldColors(),
                modifier = Modifier.fillMaxWidth()
            )
            Spacer(Modifier.height(14.dp))
            OutlinedTextField(
                value = password,
                onValueChange = { password = it },
                label = { Text("Şifre") },
                singleLine = true,
                enabled = !isLoading,
                visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                trailingIcon = {
                    IconButton(onClick = { passwordVisible = !passwordVisible }) {
                        Icon(
                            if (passwordVisible) Icons.Filled.VisibilityOff else Icons.Filled.Visibility,
                            contentDescription = null,
                            tint = TextGray
                        )
                    }
                },
                colors = loginFieldColors(),
                modifier = Modifier.fillMaxWidth(),
                keyboardActions = KeyboardActions(onDone = { if (canSubmit) onLogin(username, password) }),
                keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(
                    imeAction = androidx.compose.ui.text.input.ImeAction.Done
                )
            )

            AnimatedVisibility(visible = errorMessage != null) {
                Column {
                    Spacer(Modifier.height(16.dp))
                    Surface(
                        color = Red.copy(0.12f),
                        shape = RoundedCornerShape(10.dp),
                        border = BorderStroke(1.dp, Red.copy(0.3f))
                    ) {
                        Text(
                            errorMessage ?: "",
                            color = Red,
                            fontSize = 13.sp,
                            lineHeight = 18.sp,
                            modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)
                        )
                    }
                }
            }

            Spacer(Modifier.height(28.dp))

            if (isLoading) {
                CircularProgressIndicator(strokeWidth = 2.5.dp, color = Cyan, modifier = Modifier.size(32.dp))
                Spacer(Modifier.height(28.dp))
            } else {
                GlowButton("Giriş Yap", Cyan, enabled = canSubmit) { onLogin(username, password) }
                Spacer(Modifier.height(28.dp))
            }

            BodyText("Hesabınız her zaman tek bir cihazda aktif olur.", TextDim)
            Spacer(Modifier.height(40.dp))
        }
    }
}

@Composable
private fun loginFieldColors() = OutlinedTextFieldDefaults.colors(
    focusedTextColor = TextWhite,
    unfocusedTextColor = TextWhite,
    focusedBorderColor = Cyan,
    unfocusedBorderColor = CardBorder,
    focusedLabelColor = Cyan,
    unfocusedLabelColor = TextGray,
    cursorColor = Cyan
)

@Composable
private fun AppRoot(viewModel: FccViewModel) {
    var showSplash by remember { mutableStateOf(true) }

    if (showSplash) {
        SplashScreen(onFinished = { showSplash = false })
        return
    }

    val context = androidx.compose.ui.platform.LocalContext.current
    // Raw, instantaneous reading straight from the NetworkCallback.
    var rawOnline by remember { mutableStateOf(hasInternetConnection(context)) }
    // Debounced/gated reading that actually drives the UI — see below.
    var isOnline by remember { mutableStateOf(rawOnline) }

    // Live-updates rawOnline as the network comes and goes.
    DisposableEffect(Unit) {
        val cm = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        val callback = object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                rawOnline = hasInternetConnection(context)
            }
            override fun onLost(network: Network) {
                rawOnline = hasInternetConnection(context)
            }
            override fun onCapabilitiesChanged(network: Network, networkCapabilities: NetworkCapabilities) {
                rawOnline = hasInternetConnection(context)
            }
        }
        val request = NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .build()
        cm.registerNetworkCallback(request, callback)
        onDispose { cm.unregisterNetworkCallback(callback) }
    }

    // Reconnects apply instantly, but a drop only locks the UI after it has
    // held for 3s straight — this rides out brief blips (tower handoffs, Wi-Fi
    // "validating" hiccups) without interrupting an in-progress hardware
    // operation. Any flicker back to true during the wait cancels the lock.
    LaunchedEffect(rawOnline) {
        if (rawOnline) {
            isOnline = true
        } else {
            kotlinx.coroutines.delay(3000)
            if (!hasInternetConnection(context)) {
                isOnline = false
            }
        }
    }

    if (!isOnline) {
        NoInternetScreen(onRetry = {
            rawOnline = hasInternetConnection(context)
            isOnline = rawOnline
        })
        return
    }

    val authScope = rememberCoroutineScope()
    var authState by remember { mutableStateOf<AuthUiState>(AuthUiState.Checking) }
    var isLoggingIn by remember { mutableStateOf(false) }

    // Runs once per (re)connect: if we have a saved token, ask the server
    // whether it's still valid (account may have been deactivated, expired,
    // or reset from the admin panel since the last launch).
    LaunchedEffect(Unit) {
        val token = AuthManager.getToken(context)
        if (token == null) {
            authState = AuthUiState.LoggedOut()
            return@LaunchedEffect
        }
        when (val result = withContext(Dispatchers.IO) { AuthApi.me(token) }) {
            is AuthResult.Success -> authState = AuthUiState.LoggedIn(result.data)
            is AuthResult.Failure -> {
                AuthManager.clearSession(context)
                authState = AuthUiState.LoggedOut()
            }
        }
    }

    // While logged in, periodically re-check with the server so a remote
    // deactivation/expiry/device-reset kicks the user out without needing
    // to force-close and reopen the app.
    LaunchedEffect(authState) {
        if (authState !is AuthUiState.LoggedIn) return@LaunchedEffect
        while (true) {
            delay(5 * 60 * 1000L)
            val token = AuthManager.getToken(context) ?: break
            val result = withContext(Dispatchers.IO) { AuthApi.me(token) }
            if (result is AuthResult.Failure) {
                AuthManager.clearSession(context)
                authState = AuthUiState.LoggedOut(result.error.message)
                break
            }
        }
    }

    // Heartbeat: her 60 saniyede sunucuya sinyal gönder (online takibi)
    LaunchedEffect(authState) {
        if (authState !is AuthUiState.LoggedIn) return@LaunchedEffect
        while (true) {
            val token = AuthManager.getToken(context) ?: break
            withContext(Dispatchers.IO) { AuthApi.heartbeat(token) }
            delay(60_000L)
        }
    }

    val onLogout: () -> Unit = {
        val token = AuthManager.getToken(context)
        // Stop heartbeat/me loops immediately, then revoke server session before clearing local token.
        authState = AuthUiState.LoggedOut()
        if (token != null) {
            authScope.launch(Dispatchers.IO) {
                AuthApi.logout(token)
                AuthManager.clearSession(context)
            }
        } else {
            AuthManager.clearSession(context)
        }
    }

    when (authState) {
        AuthUiState.Checking -> {
            AuthCheckingScreen()
            return
        }
        is AuthUiState.LoggedOut -> {
            LoginScreen(
                isLoading = isLoggingIn,
                errorMessage = (authState as AuthUiState.LoggedOut).error,
                onLogin = { username, password ->
                    authState = AuthUiState.LoggedOut() // clear any previous error
                    isLoggingIn = true
                    authScope.launch {
                        val deviceId = AuthManager.getDeviceId(context)
                        val deviceName = "${Build.MANUFACTURER} ${Build.MODEL}".trim()
                        val result = withContext(Dispatchers.IO) {
                            AuthApi.login(username, password, deviceId, deviceName, FccViewModel.APP_VERSION)
                        }
                        isLoggingIn = false
                        when (result) {
                            is AuthResult.Success -> {
                                AuthManager.saveSession(context, result.data.token, result.data.member)
                                authState = AuthUiState.LoggedIn(result.data.member)
                            }
                            is AuthResult.Failure -> authState = AuthUiState.LoggedOut(result.error.message)
                        }
                    }
                }
            )
            return
        }
        is AuthUiState.LoggedIn -> Unit // fall through to the main app below
    }

    val loggedInMember = (authState as AuthUiState.LoggedIn).member

    val state by viewModel.state.collectAsStateWithLifecycle()
    val pagerState = rememberPagerState(initialPage = 0) { 5 }
    val scope = rememberCoroutineScope()

    val entrance = remember { Animatable(0f) }
    LaunchedEffect(Unit) {
        entrance.animateTo(1f, tween(700, easing = EaseOutCubic))
    }

    val tabNames = listOf("fcc", "info", "log", "update", "support")
    LaunchedEffect(pagerState.currentPage) {
        TelemetryCollector.trackTab(tabNames.getOrElse(pagerState.currentPage) { "unknown" })
    }

    // Forced update gate — blocks the entire app when a mandatory update is pending
    if (state.isUpdateForced && state.updateAvailable && state.updateInfo != null) {
        ForceUpdateScreen(state, viewModel)
        return
    }

    // Optional update dialog — shown once per session when a non-forced update is available
    var updateDialogDismissed by remember { mutableStateOf(false) }
    if (state.updateAvailable && !state.isUpdateForced && !updateDialogDismissed && state.updateInfo != null) {
        OptionalUpdateDialog(
            info = state.updateInfo!!,
            onDismiss = { updateDialogDismissed = true },
            onUpdate = {
                updateDialogDismissed = true
                scope.launch { pagerState.animateScrollToPage(3) }
            }
        )
    }

    // Notification dialog
    if (state.showNotificationDialog && state.currentNotification != null) {
        NotificationDialog(
            notification = state.currentNotification!!,
            onDismiss = { viewModel.dismissNotification() }
        )
    }

    BoxWithConstraints(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    listOf(Color(0xFF050A1A), BgDark, BgMid, BgDark),
                    startY = 0f,
                    endY = Float.POSITIVE_INFINITY
                )
            )
            .alpha(entrance.value)
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(350.dp)
                .align(Alignment.TopCenter)
                .background(
                    Brush.radialGradient(
                        listOf(Cyan.copy(0.08f), Purple.copy(0.03f), Color.Transparent),
                        center = Offset(0f, 0f),
                        radius = 800f
                    )
                )
        )
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(200.dp)
                .align(Alignment.BottomCenter)
                .background(
                    Brush.radialGradient(
                        listOf(Purple.copy(0.04f), Color.Transparent),
                        center = Offset(Float.POSITIVE_INFINITY, Float.POSITIVE_INFINITY),
                        radius = 500f
                    )
                )
        )

        HorizontalPager(
            state = pagerState,
            modifier = Modifier.fillMaxSize(),
            userScrollEnabled = true
        ) { page ->
            when (page) {
                0 -> FccPage(state, viewModel)
                1 -> InfoPage(state, viewModel)
                2 -> LogPage(state)
                3 -> UpdatePage(state, viewModel)
                4 -> SupportPage(member = loggedInMember, onLogout = onLogout)
            }
        }

        BottomNavBar(
            currentPage = pagerState.currentPage,
            onPageSelected = { index ->
                scope.launch { pagerState.animateScrollToPage(index) }
            },
            modifier = Modifier.align(Alignment.BottomCenter)
        )
    }
}

@Composable
private fun NoInternetScreen(onRetry: () -> Unit) {
    val pulse = rememberInfiniteTransition(label = "noNet")
    val glowAlpha by pulse.animateFloat(
        0.15f, 0.35f,
        infiniteRepeatable(tween(1600, easing = EaseInOutSine), RepeatMode.Reverse),
        label = "noNetGlow"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(listOf(Color(0xFF050A1A), BgDark, BgMid, BgDark))
            ),
        contentAlignment = Alignment.Center
    ) {
        Box(
            Modifier.size(280.dp).background(
                Brush.radialGradient(listOf(Red.copy(glowAlpha), Color.Transparent), radius = 400f)
            )
        )

        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(horizontal = 32.dp)
        ) {
            Icon(Icons.Outlined.CloudOff, null, tint = Red, modifier = Modifier.size(72.dp))
            Spacer(Modifier.height(24.dp))
            Text(
                "İnternet Bağlantısı Gerekli",
                color = TextWhite,
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center
            )
            Spacer(Modifier.height(12.dp))
            Text(
                "Bu uygulamayı kullanabilmek için aktif bir internet bağlantısı gereklidir. Lütfen Wi-Fi veya mobil verinizi açın ve tekrar deneyin.",
                color = TextGray,
                fontSize = 14.sp,
                lineHeight = 21.sp,
                textAlign = TextAlign.Center
            )
            Spacer(Modifier.height(32.dp))
            GlowButton("Tekrar Dene", Cyan, onClick = onRetry)
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Page 1: FCC
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun FccPage(state: AppState, viewModel: FccViewModel) {
    val context = androidx.compose.ui.platform.LocalContext.current
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 24.dp)
            .padding(bottom = BottomNavHeight + 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(56.dp))
        AppHeader(state.controllerModel)
        Spacer(Modifier.height(28.dp))
        ConnectionPill(state)
        Spacer(Modifier.height(28.dp))

        GlowCard {
            ModeBadge(state)
            Spacer(Modifier.height(20.dp))

            when {
                state.isBusy -> {
                    ProgressDisplay(state.busyProgress, state.message)
                }
                !state.isConnected -> {
                    BodyText("Drone'u kumandaya bağlayın ve ardından açın.")
                    Spacer(Modifier.height(20.dp))
                    GlowButton("Bağlan", Cyan, enabled = !state.isHardwareBusy) {
                        try {
                            MediaPlayer.create(context, R.raw.kimse_gormeden)?.apply {
                                setOnCompletionListener { it.release() }
                                start()
                                android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
                                    try {
                                        if (isPlaying) {
                                            stop()
                                        }
                                        release()
                                    } catch (_: Exception) {}
                                }, 5000)
                            }
                        } catch (_: Exception) {}
                        viewModel.connect()
                    }
                }
                state.isFccEnabled -> {
                    BodyText("FCC modu aktif.", Green)
                    Spacer(Modifier.height(20.dp))
                    GlowButton("FCC Modunu Durdur", Red, enabled = !state.isHardwareBusy) { viewModel.disableFcc() }
                    Spacer(Modifier.height(12.dp))
                    GlowButton("FCC'yi Yeniden Uygula", Cyan, filled = false, enabled = !state.isHardwareBusy) { viewModel.enableFcc() }
                    Spacer(Modifier.height(12.dp))
                    GlowButton("DJI Fly'ı Başlat", Green, filled = false, enabled = !state.isHardwareBusy) {
                        viewModel.launchDjiFly()
                    }
                    Spacer(Modifier.height(16.dp))
                    // Keepalive toggle
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text("Canlı Tutma", color = TextWhite, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                            Spacer(Modifier.height(2.dp))
                            Text(
                                if (state.isKeepaliveRunning) "CE sıfırlamasını önlemek için FCC her 2 saniyede yeniden uygulanıyor"
                                else "DJI Fly çalışırken FCC'yi aktif tut",
                                color = if (state.isKeepaliveRunning) Green else TextGray,
                                fontSize = 11.sp,
                                lineHeight = 15.sp
                            )
                        }
                        Spacer(Modifier.width(12.dp))
                        Switch(
                            checked = state.isKeepaliveRunning,
                            onCheckedChange = { enabled ->
                                if (enabled) viewModel.startKeepalive() else viewModel.stopKeepalive()
                            },
                            colors = SwitchDefaults.colors(
                                checkedThumbColor = Green,
                                checkedTrackColor = Green.copy(0.3f),
                                uncheckedThumbColor = TextGray,
                                uncheckedTrackColor = BgLight
                            )
                        )
                    }
                }
                else -> {
                    var showFlightGroupDialog by remember { mutableStateOf(false) }

                    if (state.message.isNotEmpty()) {
                        BodyText(state.message)
                        Spacer(Modifier.height(20.dp))
                    } else {
                        BodyText("FCC modunu etkinleştirmek için aşağıdaki düğmeye dokunun.")
                        Spacer(Modifier.height(20.dp))
                    }
                    GlowButton("FCC Modunu Etkinleştir", Cyan, enabled = !state.isHardwareBusy) {
                        showFlightGroupDialog = true
                    }

                    if (showFlightGroupDialog) {
                        FlightGroupNoticeDialog(
                            onConfirm = {
                                showFlightGroupDialog = false
                                viewModel.enableFcc()
                            },
                            onDismiss = { showFlightGroupDialog = false }
                        )
                    }
                }
            }

            if (state.aircraftSerial.isNotEmpty()) {
                Spacer(Modifier.height(16.dp))
                SerialRow(state.aircraftSerial, enabled = !state.isHardwareBusy) { viewModel.probeSerial() }
            }
        }

        Spacer(Modifier.height(16.dp))

        AnimatedVisibility(
            visible = state.isConnected,
            enter = fadeIn(tween(300)) + expandVertically(tween(300)),
            exit = fadeOut(tween(200)) + shrinkVertically(tween(200))
        ) {
            Column {
                GlowCard {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        SignalWaveIcon(
                            active = false,
                            color = Amber,
                            modifier = Modifier.size(28.dp)
                        )
                        Spacer(Modifier.width(12.dp))
                        Text(
                            "4G Modu",
                            color = TextWhite,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.weight(1f)
                        )
                    }
                    Spacer(Modifier.height(12.dp))
                    BodyText(
                        if (state.fourGMessage.isNotEmpty()) state.fourGMessage
                        else "Uçağa 4G etkinleştirme çerçeveleri gönderir. Durum okunmaz — DJI Fly uygulamasını veya Hücresel Dongle'ı kontrol edin.",
                        TextGray
                    )
                    Spacer(Modifier.height(20.dp))

                    if (state.is4gBusy) {
                        ProgressDisplay(state.busyProgress, "4G etkinleştirme çerçeveleri gönderiliyor...")
                    } else {
                        GlowButton("4G Etkinleştirme Çerçevelerini Gönder", Amber, enabled = !state.isHardwareBusy) {
                            viewModel.send4gActivationFrames()
                        }
                    }
                }
                Spacer(Modifier.height(16.dp))
            }
        }

        // LED control card
        Spacer(Modifier.height(16.dp))
        GlowCard {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text("Harici LED", color = TextWhite, fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        "Uçak kol LED'lerini açın veya kapatın. Uçak bağlıyken DJI Fly çalışıyor olmalı.",
                        color = TextGray,
                        fontSize = 12.sp,
                        lineHeight = 17.sp
                    )
                    if (state.ledStatus.isNotEmpty()) {
                        Spacer(Modifier.height(6.dp))
                        Text(
                            "Durum: ${state.ledStatus}",
                            color = if (state.ledStatus == "AÇIK") Green else if (state.ledStatus == "KAPALI") TextGray else Amber,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
            Spacer(Modifier.height(16.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Button(
                    onClick = { viewModel.setLed(true) },
                    enabled = state.isConnected && !state.isLedBusy && !state.isHardwareBusy,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Green,
                        contentColor = BgDark,
                        disabledContainerColor = Green.copy(0.2f),
                        disabledContentColor = Green.copy(0.4f)
                    ),
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.dp, Green.copy(0.3f)),
                    modifier = Modifier.weight(1f).height(48.dp)
                ) {
                    Text("LED AÇ", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                }
                Button(
                    onClick = { viewModel.setLed(false) },
                    enabled = state.isConnected && !state.isLedBusy && !state.isHardwareBusy,
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color.Transparent,
                        contentColor = TextGray,
                        disabledContainerColor = TextGray.copy(0.1f),
                        disabledContentColor = TextGray.copy(0.3f)
                    ),
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.5.dp, TextGray.copy(0.5f)),
                    modifier = Modifier.weight(1f).height(48.dp)
                ) {
                    Text("LED KAPAT", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                }
            }
        }

        // Auto-FCC toggle card
        Spacer(Modifier.height(16.dp))
        GlowCard {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text("Otomatik FCC", color = TextWhite, fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        "Otomatik bağlan, FCC uygula, canlı tutmayı başlat ve DJI Fly'ı aç.",
                        color = TextGray,
                        fontSize = 12.sp,
                        lineHeight = 17.sp
                    )
                }
                Spacer(Modifier.width(16.dp))
                Switch(
                    checked = state.autoFcc,
                    onCheckedChange = { viewModel.toggleAutoFcc() },
                    colors = SwitchDefaults.colors(
                        checkedThumbColor = Cyan,
                        checkedTrackColor = Cyan.copy(0.3f),
                        uncheckedThumbColor = TextGray,
                        uncheckedTrackColor = BgLight
                    )
                )
            }
        }
    }
}
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun InfoPage(state: AppState, viewModel: FccViewModel) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 24.dp)
            .padding(bottom = BottomNavHeight + 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(56.dp))
        PageTitle("Cihaz Bilgisi", Icons.Outlined.Info)
        Spacer(Modifier.height(28.dp))

        GlowCard {
            Text("Bağlantı", color = TextWhite, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(16.dp))
            InfoRow("Kumanda", state.controllerModel.ifEmpty { "Bilinmiyor" })
            Spacer(Modifier.height(10.dp))
            DividerLine()
            Spacer(Modifier.height(10.dp))
            InfoRow(
                "Durum",
                if (state.isConnected) "Bağlı" else "Bağlı Değil",
                valueColor = if (state.isConnected) Green else TextGray
            )
            Spacer(Modifier.height(10.dp))
            DividerLine()
            Spacer(Modifier.height(10.dp))
            InfoRow("Uçak S/N", state.aircraftSerial.ifEmpty { "Algılanmadı" })
        }

        Spacer(Modifier.height(16.dp))

        GlowCard {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
                modifier = Modifier.fillMaxWidth()
            ) {
                Text("Sürüm Bilgisi", color = TextWhite, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                IconButton(
                    onClick = { viewModel.queryDeviceInfo() },
                    enabled = state.isConnected && !state.isQueryingInfo && !state.isHardwareBusy,
                    modifier = Modifier.size(40.dp)
                ) {
                    if (state.isQueryingInfo) {
                        CircularProgressIndicator(
                            strokeWidth = 2.dp,
                            color = Cyan,
                            modifier = Modifier.size(22.dp)
                        )
                    } else {
                        Icon(Icons.Default.Refresh, "Sorgula", tint = Cyan, modifier = Modifier.size(24.dp))
                    }
                }
            }
            Spacer(Modifier.height(12.dp))

            if (state.deviceInfo.isNotEmpty()) {
                Text(
                    state.deviceInfo,
                    color = TextGray,
                    fontSize = 12.sp,
                    fontFamily = FontFamily.Monospace,
                    lineHeight = 20.sp,
                    modifier = Modifier.fillMaxWidth()
                )
            } else if (!state.isConnected) {
                BodyText("Önce kumandaya bağlanın.", TextDim)
            } else {
                BodyText("Sürüm bilgisi için yenile düğmesine dokunun.")
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Page 3: Log
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun LogPage(state: AppState) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 24.dp)
            .padding(bottom = BottomNavHeight + 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(56.dp))
        PageTitle("Etkinlik Günlüğü", Icons.Outlined.History)
        Spacer(Modifier.height(28.dp))

        GlowCard {
            if (state.logMessages.isEmpty()) {
                Box(
                    modifier = Modifier.fillMaxWidth().padding(vertical = 48.dp),
                    contentAlignment = Alignment.Center
                ) {
                    BodyText("Henüz etkinlik yok.", TextDim)
                }
            } else {
                Column(
                    Modifier
                        .fillMaxWidth()
                        .heightIn(max = 600.dp)
                        .verticalScroll(rememberScrollState())
                ) {
                    state.logMessages.forEachIndexed { index, entry ->
                        val color = when {
                            entry.contains("etkinleştirildi", true) ||
                            entry.contains("aktif", true) ||
                            entry.contains("bağlandı", true) ||
                            entry.contains("bağlı", true) ||
                            entry.contains("geri yüklendi", true) ||
                            entry.contains("alındı", true) -> Green

                            entry.contains("başarısız", true) ||
                            entry.contains("hata", true) -> Red

                            entry.contains("Etkinleştiriliyor", true) ||
                            entry.contains("Geri yükleniyor", true) ||
                            entry.contains("Sorgulanıyor", true) ||
                            entry.contains("Yüklendi", true) -> Amber

                            else -> Cyan.copy(0.6f)
                        }
                        if (index > 0) {
                            Spacer(Modifier.height(2.dp))
                            DividerLine(alpha = 0.3f)
                            Spacer(Modifier.height(2.dp))
                        }
                        Text(
                            entry,
                            color = color,
                            fontSize = 11.sp,
                            fontFamily = FontFamily.Monospace,
                            modifier = Modifier.padding(vertical = 6.dp)
                        )
                    }
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Forced Update Screen
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun ForceUpdateScreen(state: AppState, viewModel: FccViewModel) {
    val pulse = rememberInfiniteTransition(label = "forceUp")
    val glowAlpha by pulse.animateFloat(
        0.15f, 0.4f,
        infiniteRepeatable(tween(1600, easing = EaseInOutSine), RepeatMode.Reverse),
        label = "forceGlow"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(listOf(Color(0xFF050A1A), BgDark, BgMid, BgDark))
            ),
        contentAlignment = Alignment.Center
    ) {
        Box(
            Modifier.size(300.dp).background(
                Brush.radialGradient(listOf(Red.copy(glowAlpha * 0.5f), Color.Transparent), radius = 400f)
            )
        )

        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 32.dp)
        ) {
            Icon(Icons.Filled.SystemUpdate, null, tint = Red, modifier = Modifier.size(72.dp))
            Spacer(Modifier.height(24.dp))
            Text(
                "Zorunlu Güncelleme",
                color = TextWhite,
                fontSize = 24.sp,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center
            )
            Spacer(Modifier.height(12.dp))
            Text(
                "Uygulamayı kullanmaya devam etmek için güncelleme yapmanız gerekmektedir.",
                color = TextGray,
                fontSize = 14.sp,
                lineHeight = 21.sp,
                textAlign = TextAlign.Center
            )

            state.updateInfo?.let { info ->
                Spacer(Modifier.height(24.dp))
                GlowCard {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Yeni Sürüm:", color = TextGray, fontSize = 13.sp)
                        Text("v${info.version}", color = Green, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                    }
                    if (info.apkSize > 0) {
                        Spacer(Modifier.height(8.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text("Boyut:", color = TextGray, fontSize = 13.sp)
                            Text(
                                "%.1f MB".format(info.apkSize / 1048576.0),
                                color = TextWhite, fontSize = 13.sp
                            )
                        }
                    }
                    if (info.changelog.isNotEmpty()) {
                        Spacer(Modifier.height(12.dp))
                        DividerLine()
                        Spacer(Modifier.height(12.dp))
                        Text("Değişiklikler", color = Cyan, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(8.dp))
                        Text(info.changelog, color = TextGray, fontSize = 12.sp, lineHeight = 19.sp)
                    }
                }
            }

            Spacer(Modifier.height(32.dp))

            when {
                state.isDownloadingUpdate -> {
                    ProgressDisplay(
                        state.updateDownloadProgress,
                        "İndiriliyor... (${(state.updateDownloadProgress * 100).toInt()}%)"
                    )
                }
                state.isUpdateDownloaded -> {
                    if (state.needsInstallPermission) {
                        Text(
                            "Kurulum için bilinmeyen uygulamalardan yükleme izni gerekli.",
                            color = Amber,
                            fontSize = 13.sp,
                            textAlign = TextAlign.Center
                        )
                        Spacer(Modifier.height(12.dp))
                        GlowButton("İzni Ver ve Yükle", Green) { viewModel.installUpdate() }
                    } else {
                        GlowButton("Güncellemeyi Yükle", Green) { viewModel.installUpdate() }
                    }
                    Spacer(Modifier.height(12.dp))
                    GlowButton("Tekrar İndir", Cyan, filled = false) { viewModel.downloadUpdate() }
                }
                else -> {
                    GlowButton("Güncellemeyi İndir", Green) { viewModel.downloadUpdate() }
                }
            }

            Spacer(Modifier.height(40.dp))
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Optional Update Dialog
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun FlightGroupNoticeDialog(
    onConfirm: () -> Unit,
    onDismiss: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = CardBg,
        titleContentColor = TextWhite,
        textContentColor = TextGray,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.Groups, null, tint = Amber, modifier = Modifier.size(24.dp))
                Spacer(Modifier.width(10.dp))
                Text("Uçuş Bildirimi", fontWeight = FontWeight.Bold, fontSize = 16.sp)
            }
        },
        text = {
            Column {
                Text(
                    "ŞANLIURFA DRONE PİLOTLARI grubuna uçuş bilgisi gönderilecektir.",
                    color = TextWhite,
                    fontSize = 14.sp,
                    lineHeight = 21.sp
                )
                Spacer(Modifier.height(10.dp))
                Text(
                    "Cihaz modeli ve konum (il / ilçe / mahalle) bilgisi gruba iletilecektir. Uçuş bitince tamamlanma mesajı da gönderilir.",
                    color = TextGray,
                    fontSize = 12.sp,
                    lineHeight = 18.sp
                )
            }
        },
        confirmButton = {
            Button(
                onClick = onConfirm,
                colors = ButtonDefaults.buttonColors(containerColor = Cyan, contentColor = BgDark)
            ) {
                Text("Tamam", fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Vazgeç", color = TextGray)
            }
        }
    )
}

@Composable
private fun OptionalUpdateDialog(
    info: UpdateInfo,
    onDismiss: () -> Unit,
    onUpdate: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = CardBg,
        titleContentColor = TextWhite,
        textContentColor = TextGray,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.NewReleases, null, tint = Green, modifier = Modifier.size(24.dp))
                Spacer(Modifier.width(10.dp))
                Text("Güncelleme Mevcut", fontWeight = FontWeight.Bold)
            }
        },
        text = {
            Column {
                Text(
                    "Yeni sürüm v${info.version} yayınlandı.",
                    color = TextWhite,
                    fontSize = 14.sp
                )
                if (info.changelog.isNotEmpty()) {
                    Spacer(Modifier.height(12.dp))
                    Text(info.changelog, color = TextGray, fontSize = 12.sp, lineHeight = 18.sp)
                }
            }
        },
        confirmButton = {
            Button(
                onClick = onUpdate,
                colors = ButtonDefaults.buttonColors(containerColor = Green, contentColor = BgDark)
            ) {
                Text("Güncelle", fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Şimdi Değil", color = TextGray)
            }
        }
    )
}

// ═══════════════════════════════════════════════════════════════════════
// Notification Dialog
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun NotificationDialog(
    notification: AppNotification,
    onDismiss: () -> Unit
) {
    val iconColor = when (notification.type) {
        "warning" -> Amber
        "update" -> Green
        "promo" -> Purple
        else -> Cyan
    }
    val icon = when (notification.type) {
        "warning" -> Icons.Filled.Warning
        "update" -> Icons.Filled.SystemUpdate
        "promo" -> Icons.Filled.Campaign
        else -> Icons.Filled.Info
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = CardBg,
        titleContentColor = TextWhite,
        textContentColor = TextGray,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(icon, null, tint = iconColor, modifier = Modifier.size(24.dp))
                Spacer(Modifier.width(10.dp))
                Text(notification.title, fontWeight = FontWeight.Bold, fontSize = 16.sp)
            }
        },
        text = {
            Text(notification.message, color = TextGray, fontSize = 14.sp, lineHeight = 21.sp)
        },
        confirmButton = {
            Button(
                onClick = onDismiss,
                colors = ButtonDefaults.buttonColors(containerColor = iconColor, contentColor = BgDark)
            ) {
                Text("Tamam", fontWeight = FontWeight.Bold)
            }
        }
    )
}

// ═══════════════════════════════════════════════════════════════════════
// Page 4: Update
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun UpdatePage(state: AppState, viewModel: FccViewModel) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 24.dp)
            .padding(bottom = BottomNavHeight + 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(56.dp))
        PageTitle("Güncellemeler", Icons.Outlined.SystemUpdate)

        if (state.isCheckingUpdate) {
            Spacer(Modifier.height(28.dp))
            GlowCard {
                Column(
                    Modifier.fillMaxWidth(),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    CircularProgressIndicator(strokeWidth = 2.5.dp, color = Cyan, modifier = Modifier.size(40.dp))
                    Spacer(Modifier.height(16.dp))
                    BodyText("Sunucuda en son sürüm kontrol ediliyor...", Cyan)
                }
            }
            return@Column
        }

        val info = state.updateInfo
        if (state.updateCheckFailed && info == null) {
            Spacer(Modifier.height(28.dp))
            GlowCard {
                Column(
                    Modifier.fillMaxWidth(),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Icon(Icons.Outlined.CloudOff, null, tint = TextDim, modifier = Modifier.size(44.dp))
                    Spacer(Modifier.height(14.dp))
                    BodyText("Güncellemeler kontrol edilemedi.", TextGray)
                    Spacer(Modifier.height(6.dp))
                    Text(
                        "Wi-Fi'ye bağlı olduğunuzdan emin olun ve tekrar deneyin.",
                        color = TextDim, fontSize = 12.sp, lineHeight = 17.sp
                    )
                    Spacer(Modifier.height(20.dp))
                    GlowButton("Tekrar Dene", Cyan) { viewModel.checkForUpdates() }
                }
            }
            return@Column
        }

        if (info == null && state.updateChecked && !state.updateCheckFailed) {
            Spacer(Modifier.height(28.dp))
            GlowCard {
                Column(
                    Modifier.fillMaxWidth(),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Icon(Icons.Filled.CheckCircle, null, tint = Green, modifier = Modifier.size(44.dp))
                    Spacer(Modifier.height(14.dp))
                    BodyText("Uygulama güncel", Green)
                    Spacer(Modifier.height(6.dp))
                    Text(
                        "v${FccViewModel.APP_VERSION} — En son sürümü kullanıyorsunuz.",
                        color = TextDim, fontSize = 12.sp, lineHeight = 17.sp
                    )
                    Spacer(Modifier.height(20.dp))
                    GlowButton("Tekrar Kontrol Et", Cyan) { viewModel.checkForUpdates() }
                }
            }
            return@Column
        }

        if (info == null) return@Column

        Spacer(Modifier.height(28.dp))

        GlowCard {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        if (state.updateAvailable) "Güncelleme Mevcut" else "Güncel",
                        color = if (state.updateAvailable) Green else TextGray,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold
                    )
                    if (state.isUpdateForced && state.updateAvailable) {
                        Spacer(Modifier.height(4.dp))
                        Surface(
                            color = Red.copy(0.15f),
                            shape = RoundedCornerShape(6.dp),
                            border = BorderStroke(1.dp, Red.copy(0.3f))
                        ) {
                            Text(
                                "ZORUNLU",
                                color = Red,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Black,
                                letterSpacing = 1.sp,
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp)
                            )
                        }
                    }
                    Spacer(Modifier.height(4.dp))
                    Text(
                        "Mevcut: v${FccViewModel.APP_VERSION}",
                        color = TextDim, fontSize = 12.sp
                    )
                }
                Icon(
                    if (state.updateAvailable) Icons.Filled.NewReleases else Icons.Filled.CheckCircle,
                    null,
                    tint = if (state.updateAvailable) Green else TextDim,
                    modifier = Modifier.size(36.dp)
                )
            }

            if (state.updateAvailable) {
                Spacer(Modifier.height(16.dp))
                DividerLine()
                Spacer(Modifier.height(16.dp))
            }

            if (state.updateAvailable) {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("En Son:", color = TextGray, fontSize = 13.sp)
                    Text("v${info.version}", color = Green, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                }
                Spacer(Modifier.height(10.dp))
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Yayın:", color = TextGray, fontSize = 13.sp)
                    Text(
                        info.publishedAt.split("T").firstOrNull() ?: "",
                        color = TextWhite, fontSize = 13.sp
                    )
                }
                if (info.apkSize > 0) {
                    Spacer(Modifier.height(10.dp))
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Boyut:", color = TextGray, fontSize = 13.sp)
                        Text(
                            "%.1f MB".format(info.apkSize / 1048576.0),
                            color = TextWhite, fontSize = 13.sp
                        )
                    }
                }
            }

            Spacer(Modifier.height(20.dp))
            DividerLine()
            Spacer(Modifier.height(20.dp))

            Text("Değişiklikler", color = Cyan, fontSize = 14.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(10.dp))
            if (info.changelog.isNotEmpty()) {
                Text(
                    info.changelog,
                    color = TextGray,
                    fontSize = 12.sp,
                    lineHeight = 19.sp
                )
            } else {
                BodyText("Değişiklik listesi yok.", TextDim)
            }

            if (state.updateAvailable) {
                Spacer(Modifier.height(24.dp))
                when {
                    state.isDownloadingUpdate -> {
                        ProgressDisplay(
                            state.updateDownloadProgress,
                            "İndiriliyor... (${(state.updateDownloadProgress * 100).toInt()}%)"
                        )
                    }
                    state.isUpdateDownloaded -> {
                        if (state.needsInstallPermission) {
                            Text(
                                "Kurulum için bilinmeyen uygulamalardan yükleme izni gerekli.",
                                color = Amber,
                                fontSize = 13.sp
                            )
                            Spacer(Modifier.height(12.dp))
                            GlowButton("İzni Ver ve Yükle", Green) {
                                viewModel.installUpdate()
                            }
                        } else {
                            GlowButton("Güncellemeyi Yükle", Green) {
                                viewModel.installUpdate()
                            }
                        }
                        Spacer(Modifier.height(12.dp))
                        GlowButton("Tekrar İndir", Cyan, filled = false) {
                            viewModel.downloadUpdate()
                        }
                    }
                    else -> {
                        GlowButton("İndir", Green) {
                            viewModel.downloadUpdate()
                        }
                    }
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Page 5: Support
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun SupportPage(member: MemberInfo, onLogout: () -> Unit) {
    val context = androidx.compose.ui.platform.LocalContext.current

    val textAlpha = remember { Animatable(0f) }
    val textScale = remember { Animatable(0.5f) }
    var videoFinished by remember { mutableStateOf(false) }

    LaunchedEffect(videoFinished) {
        if (videoFinished) {
            textAlpha.animateTo(1f, tween(600))
        }
    }
    LaunchedEffect(videoFinished) {
        if (videoFinished) {
            textScale.animateTo(1f, spring(dampingRatio = Spring.DampingRatioMediumBouncy))
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 24.dp)
            .padding(bottom = BottomNavHeight + 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(56.dp))
        PageTitle("Destek", Icons.Outlined.FavoriteBorder)
        Spacer(Modifier.height(24.dp))

        // Video player
        Surface(
            color = Color.Black,
            shape = RoundedCornerShape(16.dp),
            border = BorderStroke(1.dp, CardBorder),
            modifier = Modifier
                .fillMaxWidth()
                .aspectRatio(16f / 9f)
        ) {
            AndroidView(
                factory = { ctx ->
                    VideoView(ctx).apply {
                        val videoUri = Uri.parse("android.resource://${ctx.packageName}/${R.raw.on_lira}")
                        setVideoURI(videoUri)
                        setOnCompletionListener { videoFinished = true }
                        setOnPreparedListener { mp ->
                            mp.isLooping = false
                            start()
                        }
                    }
                },
                modifier = Modifier.fillMaxSize()
            )
        }

        Spacer(Modifier.height(32.dp))

        // Text that appears after video
        AnimatedVisibility(
            visible = videoFinished,
            enter = fadeIn(tween(500)) + expandVertically(tween(500))
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text(
                    "Destek mestek yok babo",
                    color = Pink,
                    fontSize = 26.sp,
                    fontWeight = FontWeight.Black,
                    textAlign = TextAlign.Center,
                    letterSpacing = 1.sp,
                    modifier = Modifier
                        .alpha(textAlpha.value)
                        .scale(textScale.value)
                )
                Spacer(Modifier.height(12.dp))
                Text(
                    "Duan yeter :D",
                    color = Green,
                    fontSize = 22.sp,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.alpha(textAlpha.value)
                )

                Spacer(Modifier.height(40.dp))

                GlowCard {
                    Text("Hakkında", color = TextWhite, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(12.dp))
                    BodyText(
                        "DJI FCC Mod, DJI kumandanıza FCC modunu açmak ve 4G'yi etkinleştirmek için DUML komutları gönderir. " +
                        "Sunucu veya lisans gerektirmeden tamamen çevrimdışı çalışır.",
                        TextGray
                    )
                    Spacer(Modifier.height(16.dp))
                    DividerLine()
                    Spacer(Modifier.height(16.dp))
                    InfoRow("Sürüm", FccViewModel.APP_VERSION)
                    Spacer(Modifier.height(12.dp))
                    InfoRow("Geliştirici", "HG")
                    Spacer(Modifier.height(12.dp))
                    InfoRow("Protokol", "DUML")
                    Spacer(Modifier.height(16.dp))
                    DividerLine()
                    Spacer(Modifier.height(16.dp))
                    BodyText("DJI ile bağlantılı değildir. Kendi riskinizle kullanın. HG Tarafından Yapılmıştır.", TextDim)
                }
            }
        }

        Spacer(Modifier.height(16.dp))
        GlowCard {
            Text("Hesabım", color = TextWhite, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(12.dp))
            InfoRow("Kullanıcı Adı", member.name?.ifBlank { null } ?: member.username)
            if (member.expiresAt != null) {
                Spacer(Modifier.height(12.dp))
                InfoRow("Üyelik Bitişi", member.expiresAt.take(10))
            }
            Spacer(Modifier.height(16.dp))
            DividerLine()
            Spacer(Modifier.height(16.dp))
            GlowButton("Çıkış Yap", Red, filled = false, onClick = onLogout)
        }

        // Show a hint while video is playing
        if (!videoFinished) {
            Spacer(Modifier.height(20.dp))
            val dotAnim = rememberInfiniteTransition(label = "dots")
            val dotAlpha by dotAnim.animateFloat(
                0.3f, 1f,
                infiniteRepeatable(tween(600), RepeatMode.Reverse),
                label = "dotAlpha"
            )
            Text(
                "Videoyu izle...",
                color = TextGray.copy(alpha = dotAlpha),
                fontSize = 14.sp,
                fontWeight = FontWeight.Medium
            )
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Shared components
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun AppHeader(model: String) {
    val glow = rememberInfiniteTransition(label = "hdr")
    val glowAlpha by glow.animateFloat(
        0.5f, 1f,
        infiniteRepeatable(tween(2400, easing = EaseInOutSine), RepeatMode.Reverse),
        label = "hdrGlow"
    )
    val accentShift by glow.animateFloat(
        0f, 1f,
        infiniteRepeatable(tween(4000, easing = EaseInOutSine), RepeatMode.Reverse),
        label = "hdrAccent"
    )

    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier.size(280.dp, 70.dp)
        ) {
            Box(
                Modifier
                    .size(280.dp, 70.dp)
                    .background(
                        Brush.radialGradient(
                            listOf(
                                Cyan.copy(glowAlpha * 0.15f),
                                Purple.copy(glowAlpha * 0.06f),
                                Color.Transparent
                            ),
                            radius = 200f
                        )
                    )
            )
            Text(
                "DJI FCC Mod",
                color = Color(
                    red = Cyan.red * (1f - accentShift * 0.3f) + Purple.red * accentShift * 0.3f,
                    green = Cyan.green * (1f - accentShift * 0.2f),
                    blue = Cyan.blue,
                    alpha = glowAlpha
                ),
                fontSize = 32.sp,
                fontWeight = FontWeight.Black,
                letterSpacing = 2.sp
            )
        }
        Spacer(Modifier.height(2.dp))
        Surface(
            color = Green.copy(0.08f),
            shape = RoundedCornerShape(12.dp),
            border = BorderStroke(0.5.dp, Green.copy(0.2f))
        ) {
            Text(
                "  HG Tarafından Yapılmıştır  ",
                color = Green.copy(0.8f),
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                letterSpacing = 1.5.sp,
                modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp)
            )
        }
        Spacer(Modifier.height(6.dp))
        Text(
            if (model.isNotEmpty()) "v${FccViewModel.APP_VERSION} · $model" else "v${FccViewModel.APP_VERSION}",
            color = TextDim,
            fontSize = 11.sp,
            fontWeight = FontWeight.Medium
        )
    }
}

@Composable
private fun PageTitle(title: String, icon: androidx.compose.ui.graphics.vector.ImageVector) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(icon, null, tint = Cyan, modifier = Modifier.size(26.dp))
        Spacer(Modifier.width(12.dp))
        Text(title, color = TextWhite, fontSize = 24.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun ConnectionPill(state: AppState) {
    val (label, color) = when {
        state.status == "connecting" -> "Bağlanıyor..." to Amber
        state.isConnected -> "Bağlı" to Green
        state.status == "error" -> "Hata" to Red
        else -> "Bağlı Değil" to TextGray
    }

    // Bounce-in on state change (no scale overflow — use alpha + small bump)
    val bounce = remember { Animatable(1f) }
    LaunchedEffect(state.isConnected) {
        if (state.isConnected) {
            bounce.snapTo(0.8f)
            bounce.animateTo(1f, spring(dampingRatio = Spring.DampingRatioMediumBouncy))
        }
    }

    // Pulsing glow when connected
    val glowAlpha: Float = if (state.isConnected) {
        val t = rememberInfiniteTransition(label = "pill")
        val a by t.animateFloat(0.1f, 0.25f, infiniteRepeatable(tween(1800), RepeatMode.Reverse), label = "pillGlow")
        a
    } else 0f

    Surface(
        color = color.copy(0.1f),
        shape = CircleShape,
        border = BorderStroke(1.dp, color.copy(0.3f)),
        modifier = Modifier
            .padding(4.dp)
            .scale(bounce.value)
            .drawBehind {
                if (glowAlpha > 0f) {
                    drawCircle(color.copy(glowAlpha), radius = size.maxDimension * 0.75f)
                }
            }
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.padding(horizontal = 24.dp, vertical = 12.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(8.dp)
                    .background(color, CircleShape)
            )
            Spacer(Modifier.width(10.dp))
            Text(label, color = color, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
        }
    }
}

@Composable
private fun ModeBadge(state: AppState) {
    val active = state.isFccEnabled
    val bgBrush = if (active) {
        Brush.horizontalGradient(listOf(Color(0xFF082A45), Color(0xFF0C3558), Color(0xFF0A2D4A)))
    } else {
        Brush.horizontalGradient(listOf(BgLight.copy(0.5f), BgLight.copy(0.25f)))
    }

    val checkScale = remember { Animatable(0f) }
    LaunchedEffect(active) {
        if (active) {
            checkScale.snapTo(0f)
            checkScale.animateTo(1.2f, spring(dampingRatio = Spring.DampingRatioMediumBouncy))
            checkScale.animateTo(1f, spring(dampingRatio = Spring.DampingRatioMediumBouncy))
        } else {
            checkScale.snapTo(0f)
        }
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(bgBrush)
            .padding(horizontal = 24.dp, vertical = 18.dp)
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                "MOD",
                color = TextDim,
                fontSize = 10.sp,
                fontWeight = FontWeight.Black,
                letterSpacing = 2.sp
            )
            Spacer(Modifier.height(4.dp))
            Text(
                if (active) "FCC" else "CE",
                color = if (active) Green else TextWhite,
                fontSize = 30.sp,
                fontWeight = FontWeight.Black
            )
            Spacer(Modifier.height(4.dp))
            Text(
                if (active) "Yüksek güç bölgesi aktif" else "Varsayılan bölge",
                color = if (active) Green.copy(0.7f) else TextGray,
                fontSize = 12.sp
            )
        }
        if (active) {
            Icon(
                Icons.Filled.CheckCircle, null, tint = Green,
                modifier = Modifier.size(44.dp).scale(checkScale.value)
            )
        } else {
            Icon(
                Icons.Outlined.Radio, null, tint = TextDim,
                modifier = Modifier.size(36.dp)
            )
        }
    }
}

@Composable
private fun ProgressDisplay(progress: Float, label: String) {
    Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(label, color = Cyan, fontSize = 14.sp, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(16.dp))
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(8.dp)
                .clip(RoundedCornerShape(4.dp))
                .background(BgLight)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth(progress)
                    .height(8.dp)
                    .clip(RoundedCornerShape(4.dp))
                    .background(Brush.horizontalGradient(listOf(Cyan, Green)))
            )
        }
        Spacer(Modifier.height(10.dp))
        Text(
            "${(progress * 100).toInt()}%",
            color = TextGray,
            fontSize = 12.sp,
            fontFamily = FontFamily.Monospace,
            fontWeight = FontWeight.Medium
        )
    }
}

@Composable
private fun BodyText(text: String, color: Color = TextGray) {
    Text(
        text,
        color = color,
        fontSize = 13.sp,
        lineHeight = 20.sp
    )
}

@Composable
private fun SerialRow(serial: String, enabled: Boolean = true, onRefresh: () -> Unit) {
    Surface(
        color = BgLight.copy(0.4f),
        shape = RoundedCornerShape(10.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp)
        ) {
            Icon(Icons.Filled.Flight, null, tint = Cyan.copy(0.6f), modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(10.dp))
            Text("S/N: ", color = TextGray, fontSize = 12.sp)
            Text(serial, color = TextWhite, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.weight(1f))
            IconButton(onClick = onRefresh, enabled = enabled, modifier = Modifier.size(24.dp)) {
                Icon(Icons.Default.Refresh, "Yenile", tint = TextGray, modifier = Modifier.size(16.dp))
            }
        }
    }
}

@Composable
private fun InfoRow(label: String, value: String, valueColor: Color = TextWhite) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(label, color = TextGray, fontSize = 13.sp)
        Text(
            value,
            color = valueColor,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            maxLines = 1
        )
    }
}

@Composable
private fun DividerLine(alpha: Float = 0.5f) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(1.dp)
            .background(CardBorder.copy(alpha))
    )
}

@Composable
private fun StatusDot(color: Color) {
    val pulse = rememberInfiniteTransition(label = "dot")
    val alpha by pulse.animateFloat(0.5f, 1f, infiniteRepeatable(tween(1200), RepeatMode.Reverse), label = "dotPulse")
    Box(
        modifier = Modifier
            .size(10.dp)
            .background(color.copy(alpha), CircleShape)
    )
}

@Composable
private fun GlowCard(content: @Composable () -> Unit) {
    Surface(
        color = Color.Transparent,
        shape = RoundedCornerShape(18.dp),
        border = BorderStroke(1.dp, CardBorder.copy(0.7f)),
        modifier = Modifier
            .fillMaxWidth()
            .background(
                Brush.verticalGradient(
                    listOf(CardBg, CardBg.copy(0.85f), Color(0xFF0E1530))
                ),
                RoundedCornerShape(18.dp)
            )
    ) {
        Column(
            modifier = Modifier
                .padding(20.dp)
                .fillMaxWidth()
        ) {
            content()
        }
    }
}

@Composable
private fun GlowButton(
    text: String,
    color: Color,
    filled: Boolean = true,
    enabled: Boolean = true,
    onClick: () -> Unit
) {
    Button(
        onClick = onClick,
        enabled = enabled,
        colors = ButtonDefaults.buttonColors(
            containerColor = if (filled) color else Color.Transparent,
            contentColor = if (filled) Color(0xFF0A0F1E) else color,
            disabledContainerColor = color.copy(0.15f),
            disabledContentColor = color.copy(0.35f)
        ),
        shape = RoundedCornerShape(14.dp),
        border = when {
            !filled && enabled -> BorderStroke(1.5.dp, color.copy(0.5f))
            filled && enabled -> BorderStroke(1.dp, color.copy(0.4f))
            else -> null
        },
        elevation = if (filled && enabled) ButtonDefaults.buttonElevation(
            defaultElevation = 6.dp, pressedElevation = 2.dp
        ) else null,
        modifier = Modifier
            .fillMaxWidth()
            .height(54.dp)
    ) {
        Text(text, fontWeight = FontWeight.Bold, fontSize = 15.sp, letterSpacing = 0.5.sp)
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Signal wave icon
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun SignalWaveIcon(active: Boolean, color: Color, modifier: Modifier = Modifier) {
    val transition = rememberInfiniteTransition(label = "wave")
    val phase by transition.animateFloat(
        0f, (2 * PI).toFloat(),
        infiniteRepeatable(tween(1200, easing = LinearEasing), RepeatMode.Restart),
        label = "wavePhase"
    )

    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val centerY = h / 2
        val amplitude = if (active) h * 0.25f else h * 0.08f
        val lineColor = if (active) color else color.copy(0.35f)

        val path = androidx.compose.ui.graphics.Path()
        for (x in 0..w.toInt() step 2) {
            val y = centerY + amplitude * sin((x / w).toDouble() * 2.0 * PI + phase.toDouble()).toFloat()
            if (x == 0) path.moveTo(x.toFloat(), y) else path.lineTo(x.toFloat(), y)
        }
        drawPath(path, lineColor, style = Stroke(width = 2.5.dp.toPx(), cap = StrokeCap.Round))
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Bottom navigation bar
// ═══════════════════════════════════════════════════════════════════════

@Composable
private fun BottomNavBar(
    currentPage: Int,
    onPageSelected: (Int) -> Unit,
    modifier: Modifier = Modifier
) {
    val tabs = listOf(
        Triple("FCC", Icons.Filled.Wifi, Cyan),
        Triple("Bilgi", Icons.Filled.Info, Green),
        Triple("Günlük", Icons.Filled.History, Amber),
        Triple("Güncelle", Icons.Filled.SystemUpdate, Purple),
        Triple("Destek", Icons.Filled.Favorite, Pink)
    )

    Surface(
        color = Color(0xE6050A1A),
        shadowElevation = 12.dp,
        modifier = modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(BottomNavHeight)
                .padding(horizontal = 16.dp),
            horizontalArrangement = Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.CenterVertically
        ) {
            tabs.forEachIndexed { index, (label, icon, color) ->
                val selected = currentPage == index

                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    modifier = Modifier
                        .weight(1f)
                        .clickable { onPageSelected(index) }
                        .padding(vertical = 8.dp)
                ) {
                    Icon(
                        icon, label,
                        tint = if (selected) color else TextDim,
                        modifier = Modifier.size(24.dp)
                    )
                    Spacer(Modifier.height(4.dp))
                    Text(
                        label,
                        color = if (selected) color else TextDim,
                        fontSize = 10.sp,
                        fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal,
                        maxLines = 1
                    )
                    Spacer(Modifier.height(4.dp))
                    Box(
                        modifier = Modifier
                            .width(24.dp)
                            .height(3.dp)
                            .clip(RoundedCornerShape(2.dp))
                            .background(if (selected) color else Color.Transparent)
                    )
                }
            }
        }
    }
}
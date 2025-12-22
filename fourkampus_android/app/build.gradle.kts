plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
    alias(libs.plugins.hilt)
    id("org.jetbrains.kotlin.kapt")
}

android {
    namespace = "com.ffoursoftware.unifour_kotlin"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.ffoursoftware.unifour_kotlin"
        minSdk = 24
        targetSdk = 36
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    buildTypes {
        release {
            isMinifyEnabled = true // Production için minify açık
            isShrinkResources = true // Kullanılmayan resource'ları kaldır
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            // Localhost Base URL (hosting bağlantısı kaldırıldı)
            buildConfigField("String", "BASE_URL", "\"https://fourkampus.com.tr/api/\"")
            buildConfigField("String", "LOCAL_BASE_URL", "\"https://fourkampus.com.tr/api/\"")
            buildConfigField("String", "PRODUCTION_BASE_URL", "\"https://fourkampus.com.tr/api/\"")
        }
        debug {
            isMinifyEnabled = false // Debug için minify kapalı (daha hızlı build)
            isShrinkResources = false
            // Debug Base URL (Android Emulator için)
            buildConfigField("String", "BASE_URL", "\"https://fourkampus.com.tr/api/\"")
            buildConfigField("String", "LOCAL_BASE_URL", "\"https://fourkampus.com.tr/api/\"")
            buildConfigField("String", "PRODUCTION_BASE_URL", "\"https://fourkampus.com.tr/api/\"")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
        isCoreLibraryDesugaringEnabled = true
    }
    kotlinOptions {
        jvmTarget = "11"
    }
    buildFeatures {
        compose = true
        buildConfig = true // BuildConfig'i etkinleştir
    }
    composeOptions {
        kotlinCompilerExtensionVersion = "1.5.14"
    }
}


dependencies {
    // Core Android
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.appcompat)
    implementation(libs.material)
    implementation(libs.androidx.activity)
    implementation(libs.androidx.constraintlayout)
    
    // Compose BOM
    implementation(platform(libs.compose.bom))
    implementation(libs.compose.ui)
    implementation(libs.compose.ui.graphics)
    implementation(libs.compose.ui.tooling.preview)
    implementation(libs.compose.material3)
    implementation(libs.compose.activity)
    implementation(libs.compose.navigation)
    
    // Material Icons Extended (for School icon)
    implementation("androidx.compose.material:material-icons-extended:1.7.0")
    
    // Lifecycle
    implementation(libs.lifecycle.runtime.ktx)
    implementation(libs.lifecycle.viewmodel.compose)
    implementation(libs.lifecycle.runtime.compose)
    implementation("androidx.lifecycle:lifecycle-process:2.8.7") // ProcessLifecycleOwner için
    
    // Network
    implementation(libs.retrofit)
    implementation(libs.retrofit.gson)
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging)
    implementation(libs.gson)
    
    // Coroutines
    implementation(libs.coroutines.core)
    implementation(libs.coroutines.android)
    
    // Hilt
    implementation(libs.hilt.android)
    kapt(libs.hilt.compiler)
    implementation(libs.hilt.navigation.compose)
    
    // Image Loading
    implementation(libs.coil.compose)
    
    // Data Storage
    implementation(libs.datastore.preferences)
    
    // Biometric
    implementation(libs.biometric)
    
    // Security Crypto
    implementation("androidx.security:security-crypto:1.1.0-alpha06")
    
    // Splash Screen
    implementation("androidx.core:core-splashscreen:1.0.1")
    
    // QR Code (ZXing)
    implementation("com.google.zxing:core:3.5.2")
    implementation("com.journeyapps:zxing-android-embedded:4.3.0")
    
    // CameraX for QR scanning
    implementation("androidx.camera:camera-camera2:1.3.1")
    implementation("androidx.camera:camera-lifecycle:1.3.1")
    implementation("androidx.camera:camera-view:1.3.1")
    
    // Core Library Desugaring (for java.time API on API < 26)
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
    
    // Firebase Cloud Messaging
    implementation(platform("com.google.firebase:firebase-bom:32.7.0"))
    implementation("com.google.firebase:firebase-messaging-ktx")
    implementation("com.google.firebase:firebase-analytics-ktx")
    
    // Google Maps
    implementation("com.google.android.gms:play-services-maps:18.2.0")
    implementation("com.google.android.gms:play-services-location:21.0.1")
    implementation("com.google.maps.android:maps-compose:4.3.0")
    implementation("com.google.maps.android:maps-compose-utils:4.3.0")
    implementation("com.google.maps.android:maps-compose-widgets:4.3.0")
    
    // Image Picker
    implementation("io.coil-kt:coil-compose:2.5.0")
    
    // Testing
    testImplementation(libs.junit)
    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(libs.androidx.espresso.core)
    androidTestImplementation(platform(libs.compose.bom))
    androidTestImplementation("androidx.compose.ui:ui-test-junit4")
    debugImplementation(libs.compose.ui.tooling)
}

# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.

# Retrofit
-keepattributes Signature, InnerClasses, EnclosingMethod
-keepattributes RuntimeVisibleAnnotations, RuntimeVisibleParameterAnnotations
-keepclassmembers,allowshrinking,allowobfuscation interface * {
    @retrofit2.http.* <methods>;
}
-dontwarn org.codehaus.mojo.animal_sniffer.IgnoreJRERequirement
-dontwarn javax.annotation.**
-dontwarn kotlin.Unit
-dontwarn retrofit2.KotlinExtensions
-dontwarn retrofit2.KotlinExtensions$*

# OkHttp
-dontwarn okhttp3.**
-dontwarn okio.**
-keepnames class okhttp3.internal.publicsuffix.PublicSuffixDatabase

# Gson
-keepattributes Signature
-keepattributes *Annotation*
-dontwarn sun.misc.**
-keep class com.google.gson.** { *; }
-keep class * implements com.google.gson.TypeAdapter
-keep class * implements com.google.gson.TypeAdapterFactory
-keep class * implements com.google.gson.JsonSerializer
-keep class * implements com.google.gson.JsonDeserializer
-keepclassmembers,allowobfuscation class * {
  @com.google.gson.annotations.SerializedName <fields>;
}

# Data classes (keep for Gson)
-keep class com.ffoursoftware.unifour_kotlin.data.model.** { *; }

# Hilt
-keep class dagger.hilt.** { *; }
-keep class javax.inject.** { *; }
-keep class * extends dagger.hilt.android.internal.managers.ViewComponentManager$FragmentContextWrapper { *; }

# Kotlin Coroutines
-keepnames class kotlinx.coroutines.internal.MainDispatcherFactory
-keepnames class kotlinx.coroutines.CoroutineExceptionHandler
-keepclassmembernames class kotlinx.** {
    volatile <fields>;
}
-dontwarn kotlinx.coroutines.**

# Coil
-keep class coil.** { *; }
-keep interface coil.** { *; }
-dontwarn coil.**

# EncryptedSharedPreferences
-keep class androidx.security.crypto.** { *; }
-dontwarn androidx.security.crypto.**

# Keep ViewModels
-keep class * extends androidx.lifecycle.ViewModel { *; }

# Keep Application class
-keep class com.ffoursoftware.unifour_kotlin.UniFourApplication { *; }

# Remove logging in release
-assumenosideeffects class android.util.Log {
    public static *** d(...);
    public static *** v(...);
    public static *** i(...);
}

# Keep native methods
-keepclasseswithmembernames class * {
    native <methods>;
}

# Keep Parcelable implementations
-keep class * implements android.os.Parcelable {
  public static final android.os.Parcelable$Creator *;
}

# Keep Serializable classes
-keepclassmembers class * implements java.io.Serializable {
    static final long serialVersionUID;
    private static final java.io.ObjectStreamField[] serialPersistentFields;
    private void writeObject(java.io.ObjectOutputStream);
    private void readObject(java.io.ObjectInputStream);
    java.lang.Object writeReplace();
    java.lang.Object readResolve();
}

# Optimization
-optimizationpasses 5
-dontusemixedcaseclassnames
-dontskipnonpubliclibraryclasses
-verbose

# Remove unused code
-assumenosideeffects class kotlin.jvm.internal.Intrinsics {
    static void checkParameterIsNotNull(java.lang.Object, java.lang.String);
}

# Keep annotations
-keepattributes *Annotation*
-keepattributes SourceFile,LineNumberTable
-keep public class * extends java.lang.Exception

# Keep SecureLogger for production debugging (masked sensitive data)
-keep class com.ffoursoftware.unifour_kotlin.util.SecureLogger { *; }

# Keep ErrorHandler for user-friendly messages
-keep class com.ffoursoftware.unifour_kotlin.util.ErrorHandler { *; }

# Keep NetworkUtils for connectivity checks
-keep class com.ffoursoftware.unifour_kotlin.util.NetworkUtils { *; }

# Keep PerformanceMonitor for analytics
-keep class com.ffoursoftware.unifour_kotlin.util.PerformanceMonitor { *; }

# Keep MemoryManager for memory optimization
-keep class com.ffoursoftware.unifour_kotlin.util.MemoryManager { *; }

# Keep LifecycleHandler for app lifecycle management
-keep class com.ffoursoftware.unifour_kotlin.util.LifecycleHandler { *; }

# Keep ResourceManager for cache management
-keep class com.ffoursoftware.unifour_kotlin.util.ResourceManager { *; }

# Keep CoroutineManager for background task management
-keep class com.ffoursoftware.unifour_kotlin.util.CoroutineManager { *; }

# Keep ConnectivityObserver for network monitoring
-keep class com.ffoursoftware.unifour_kotlin.util.ConnectivityObserver { *; }

# Keep InputValidator for input validation
-keep class com.ffoursoftware.unifour_kotlin.util.InputValidator { *; }

# Keep Constants for configuration
-keep class com.ffoursoftware.unifour_kotlin.util.Constants { *; }

# Keep all utility classes
-keep class com.ffoursoftware.unifour_kotlin.util.** { *; }

# Remove debug logging in release (SecureLogger handles this, but extra safety)
-assumenosideeffects class com.ffoursoftware.unifour_kotlin.util.SecureLogger {
    public static *** d(...);
    public static *** v(...);
    public static *** i(...);
}

# Keep API response models for Gson
-keep class com.ffoursoftware.unifour_kotlin.data.remote.** { *; }

# Keep all data models
-keep class com.ffoursoftware.unifour_kotlin.data.model.** { *; }

# Keep SecureStorage for encrypted storage
-keep class com.ffoursoftware.unifour_kotlin.data.local.SecureStorage { *; }

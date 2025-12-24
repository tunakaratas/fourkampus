//
//  LoginForm.swift
//  Four Kampüs
//
//  Created by Tuna Karataş on 8.11.2025.
//

import SwiftUI

struct LoginForm: View {
    @ObservedObject var authViewModel: AuthViewModel
    @Environment(\.colorScheme) private var colorScheme
    @State private var email = ""
    @State private var password = ""
    @FocusState private var focusedField: Field?
    
    enum Field {
        case email, password
    }
    
    private var textColor: Color {
        colorScheme == .dark ? .white : .primary
    }
    
    private var secondaryTextColor: Color {
        colorScheme == .dark ? .white.opacity(0.8) : .secondary
    }
    
    private var fieldBackground: Color {
        colorScheme == .dark ? Color.white.opacity(0.15) : Color(UIColor.secondarySystemBackground)
    }
    
    private var fieldBorder: Color {
        colorScheme == .dark ? Color.white.opacity(0.2) : Color.gray.opacity(0.2)
    }
    
    // MARK: - Computed Properties (Type-checking optimization)
    private var headerSection: some View {
        VStack(spacing: 8) {
            Text("Hoş Geldiniz")
                .font(.system(size: 28, weight: .bold, design: .rounded))
                .foregroundColor(textColor)
            
            Text("Hesabınıza giriş yapın")
                .font(.system(size: 15, weight: .medium))
                .foregroundColor(secondaryTextColor)
        }
        .padding(.bottom, 8)
    }
    
    private var emailField: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("E-posta")
                .font(.system(size: 14, weight: .medium))
                .foregroundColor(secondaryTextColor)
            
            HStack(spacing: 12) {
                Image(systemName: "envelope.fill")
                    .font(.system(size: 16))
                    .foregroundColor(colorScheme == .dark ? .white.opacity(0.6) : Color(hex: "6366f1"))
                    .frame(width: 20)
                
                TextField("ornek@email.com", text: $email)
                    .textContentType(.emailAddress)
                    .keyboardType(.emailAddress)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                    .focused($focusedField, equals: .email)
                    .foregroundColor(textColor)
                    .tint(colorScheme == .dark ? .white : Color(hex: "6366f1"))
            }
            .padding(.vertical, 14)
            .padding(.horizontal, 4)
            .background(
                RoundedRectangle(cornerRadius: 8)
                    .fill(Color.clear)
            )
            .overlay(
                Rectangle()
                    .frame(height: 2)
                    .foregroundColor(
                        focusedField == .email 
                            ? Color(hex: "6366f1")
                            : fieldBorder
                    ),
                alignment: .bottom
            )
        }
    }
    
    private var passwordField: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("Şifre")
                .font(.system(size: 14, weight: .medium))
                .foregroundColor(secondaryTextColor)
            
            HStack(spacing: 12) {
                Image(systemName: "lock.fill")
                    .font(.system(size: 16))
                    .foregroundColor(colorScheme == .dark ? .white.opacity(0.6) : Color(hex: "6366f1"))
                    .frame(width: 20)
                
                SecureField("••••••••", text: $password)
                    .textContentType(.password)
                    .focused($focusedField, equals: .password)
                    .foregroundColor(textColor)
                    .tint(colorScheme == .dark ? .white : Color(hex: "6366f1"))
            }
            .padding(.vertical, 14)
            .padding(.horizontal, 4)
            .background(
                RoundedRectangle(cornerRadius: 8)
                    .fill(Color.clear)
            )
            .overlay(
                Rectangle()
                    .frame(height: 2)
                    .foregroundColor(
                        focusedField == .password 
                            ? Color(hex: "6366f1")
                            : fieldBorder
                    ),
                alignment: .bottom
            )
        }
    }
    
    @ViewBuilder
    private var errorMessageView: some View {
        if let error = authViewModel.errorMessage, !error.isEmpty {
            HStack(spacing: 8) {
                Image(systemName: "exclamationmark.triangle.fill")
                    .font(.system(size: 14))
                Text(error)
                    .font(.system(size: 14, weight: .medium))
            }
            .foregroundColor(Color(hex: "ef4444"))
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(12)
            .padding(.leading, 4)
            .background(
                RoundedRectangle(cornerRadius: 8)
                    .fill(Color(hex: "ef4444").opacity(colorScheme == .dark ? 0.15 : 0.08))
            )
            .overlay(
                RoundedRectangle(cornerRadius: 8)
                    .frame(width: 4)
                    .foregroundColor(Color(hex: "ef4444")),
                alignment: .leading
            )
            .transition(.asymmetric(
                insertion: .move(edge: .top).combined(with: .opacity),
                removal: .move(edge: .top).combined(with: .opacity)
            ))
        }
    }
    
    private var loginButton: some View {
        Button(action: {
            focusedField = nil
            Task {
                await authViewModel.login(
                    email: email.trimmingCharacters(in: .whitespacesAndNewlines),
                    password: password
                )
            }
        }) {
            HStack(spacing: 12) {
                if authViewModel.isLoading {
                    ProgressView()
                        .tint(.white)
                } else {
                    Text("Giriş Yap")
                        .font(.system(size: 17, weight: .semibold))
                }
            }
            .foregroundColor(.white)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 16)
            .background(buttonBackground)
            .cornerRadius(10)
        }
        .disabled(authViewModel.isLoading || email.isEmpty || password.isEmpty)
    }
    
    private var buttonBackground: LinearGradient {
        if authViewModel.isLoading || email.isEmpty || password.isEmpty {
            return LinearGradient(
                colors: [Color.gray.opacity(0.4), Color.gray.opacity(0.3)],
                startPoint: .leading,
                endPoint: .trailing
            )
        } else {
            return LinearGradient(
                colors: [Color(hex: "6366f1"), Color(hex: "8b5cf6")],
                startPoint: .leading,
                endPoint: .trailing
            )
        }
    }
    
    var body: some View {
        VStack(spacing: 0) {
            VStack(spacing: 24) {
                headerSection
                emailField
                passwordField
                errorMessageView
                loginButton
            }
            .padding(24)
        }
    }
}

#Preview {
    LoginForm(authViewModel: AuthViewModel())
}

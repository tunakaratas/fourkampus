//
//  LocalizationManager.swift
//  Four Kampüs
//
//  Multi-language Support
//

import Foundation
import Combine
@preconcurrency import ObjectiveC

class LocalizationManager: ObservableObject {
    static let shared = LocalizationManager()
    
    @Published var currentLanguage: Language = .turkish
    
    enum Language: String, CaseIterable {
        case turkish = "tr"
        case english = "en"
        
        var displayName: String {
            switch self {
            case .turkish: return "Türkçe"
            case .english: return "English"
            }
        }
    }
    
    private init() {
        if let saved = UserDefaults.standard.string(forKey: "selected_language"),
           let language = Language(rawValue: saved) {
            currentLanguage = language
        }
    }
    
    func setLanguage(_ language: Language) {
        currentLanguage = language
        UserDefaults.standard.set(language.rawValue, forKey: "selected_language")
        // Update app language
        Bundle.setLanguage(language.rawValue)
    }
    
    func localizedString(_ key: String) -> String {
        return NSLocalizedString(key, comment: "")
    }
}

extension Bundle {
    nonisolated(unsafe) private static var bundleKey: UInt8 = 0
    
    class func setLanguage(_ language: String) {
        defer {
            object_setClass(Bundle.main, AnyLanguageBundle.self)
        }
        objc_setAssociatedObject(Bundle.main, &bundleKey, Bundle.main.path(forResource: language, ofType: "lproj"), .OBJC_ASSOCIATION_RETAIN_NONATOMIC)
    }
    
    nonisolated var localizedPath: String? {
        return objc_getAssociatedObject(self, &Bundle.bundleKey) as? String
    }
}

class AnyLanguageBundle: Bundle, @unchecked Sendable {
    nonisolated override func localizedString(forKey key: String, value: String?, table tableName: String?) -> String {
        guard let path = self.localizedPath,
              let bundle = Bundle(path: path) else {
            return super.localizedString(forKey: key, value: value, table: tableName)
        }
        return bundle.localizedString(forKey: key, value: value, table: tableName)
    }
    
    nonisolated override init?(path: String) {
        super.init(path: path)
    }
}


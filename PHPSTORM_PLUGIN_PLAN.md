# Maho PhpStorm Plugin - Comprehensive Analysis & Implementation Plan

**Version:** 1.0
**Date:** 2025-10-20
**Status:** Planning Phase

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Feature Analysis](#2-feature-analysis)
3. [Technical Requirements](#3-technical-requirements)
4. [Architecture Design](#4-architecture-design)
5. [Implementation Plan](#5-implementation-plan)
6. [Development Tools & Stack](#6-development-tools--stack)
7. [Priority Matrix](#7-priority-matrix)
8. [Success Metrics](#8-success-metrics)
9. [Risks & Mitigation](#9-risks--mitigation)
10. [Next Steps](#10-next-steps)

---

## 1. Executive Summary

A PhpStorm plugin for Maho would significantly enhance developer productivity by providing intelligent code completion, navigation, generation, and validation specific to Maho's architecture. The plugin would leverage the IntelliJ Platform SDK and follow patterns established by the successful Magento 2 PhpStorm plugin, adapted for Maho's unique modernizations.

### Key Differentiators

- Support for modern PHP 8.3+ features
- Integration with Doctrine DBAL instead of Zend_Db
- Recognition of Maho namespace (replacing Varien)
- Support for modern validation, HTTP client, and date handling patterns
- Pest PHP test generation support

### Development Timeline

**Estimated Duration:** 20 weeks (5 months)
**Team Size:** 1-2 developers
**Effort:** ~400-500 developer hours

---

## 2. Feature Analysis

### 2.1 Navigation Features (HIGH Priority)

#### XML to PHP Navigation

- Navigate from layout XML blocks to Block classes
- Navigate from config.xml observers to Observer classes
- Navigate from config.xml models/helpers/blocks to their PHP classes
- Navigate from system.xml to configuration source models
- Navigate from events.xml observers to Observer classes
- Navigate from di.xml (if used) to target classes

#### PHP to XML Navigation

- Navigate from Block/Model/Helper classes to their XML definitions
- Navigate from Observer classes to event registrations
- Line markers showing where classes are referenced in XML

#### Template Navigation

- Navigate from Block classes to their templates
- Navigate from templates back to their Block classes
- Navigate from layout XML template references to .phtml files

#### Route Navigation

- Navigate from controller routes to controller classes
- Navigate from URL paths to controller actions
- Navigate from XML route definitions to controllers

**Business Value:** Reduces time spent searching for related files by 50-70%

---

### 2.2 Code Generation Features (HIGH Priority)

#### Module Generation

- Generate new Maho module structure
- Create module declaration in `app/etc/modules/`
- Generate `config.xml` with proper structure
- Create module directory structure (`Block/`, `Model/`, `controllers/`, etc.)

#### Class Generation

- Generate Models with proper `Mage_Core_Model_Abstract` extension
- Generate Resource Models
- Generate Collections
- Generate Blocks with proper template references
- Generate Helpers extending `Mage_Core_Helper_Abstract`
- Generate Controllers (Frontend/Adminhtml)
- Generate Observers with proper method signature

#### XML Generation

- Generate observer registration in `config.xml`
- Generate event dispatches in PHP code
- Generate layout XML handles
- Generate `system.xml` configuration sections
- Generate `adminhtml.xml` ACL entries

#### Test Generation

- Generate Pest PHP test files with proper `use()` context
- Generate test cases for Models/Blocks/Helpers
- Support for Frontend/Backend/Install test contexts

**Business Value:** Reduces boilerplate code writing by 80%, ensures consistency

---

### 2.3 Code Completion & IntelliSense (HIGH Priority)

#### Factory Method Completion

- Autocomplete for `Mage::getModel('catalog/product')`
- Autocomplete for `Mage::helper('core')`
- Autocomplete for `Mage::getSingleton()`
- Autocomplete for `Mage::getResourceModel()`

#### Event System

- Autocomplete event names in `Mage::dispatchEvent()`
- Autocomplete event names in observer registration
- Show available event parameters

#### Layout XML

- Autocomplete block types
- Autocomplete template paths
- Autocomplete handle names
- Autocomplete action method names

#### Configuration Paths

- Autocomplete for `Mage::getStoreConfig()` paths
- Autocomplete for `system.xml` configuration paths

**Business Value:** Reduces typos by 70%, faster coding, better discoverability

---

### 2.4 Code Inspection & Validation (MEDIUM Priority)

#### Maho-Specific Validations

- Detect usage of removed `Zend_*` classes (error)
- Detect usage of `Varien_*` classes in new code (warning with quick-fix to `Maho\`)
- Detect deprecated `Varien_Date` usage (error, suggest `DateTime`)
- Validate proper use of Doctrine DBAL vs `Zend_Db`
- Validate `Maho\Db\Select` usage
- Validate proper Observer method signatures: `public function execute(Maho\Event\Observer $observer)`

#### XML Validations

- Validate `config.xml` structure
- Validate layout XML structure
- Validate that referenced classes exist
- Validate that referenced templates exist
- Validate that event observer classes/methods exist

#### Best Practices

- Detect missing `strict_types` declaration in new files
- Detect missing `#[\Override]` attributes
- Suggest use of `getUserParam()` in admin controllers
- Validate `ADMIN_RESOURCE` constant in admin controllers

**Business Value:** Catch errors before runtime, enforce best practices

---

### 2.5 Refactoring Support (MEDIUM Priority)

#### Modernization Refactorings

- Quick-fix: Convert `Varien_*` to `Maho\` namespace
- Quick-fix: Convert `Zend_Json` to `Mage::helper('core')->jsonEncode/Decode`
- Quick-fix: Convert `Zend_Date` to `DateTime`
- Quick-fix: Convert `Zend_Http_Client` to Symfony HttpClient
- Quick-fix: Add `strict_types` declaration
- Quick-fix: Add `#[\Override]` attribute

#### Rename Refactoring

- Rename model class and update XML references
- Rename helper and update all `Mage::helper()` calls
- Rename block and update layout XML

**Business Value:** Safe refactoring, automated modernization of legacy code

---

### 2.6 Live Templates & Snippets (LOW Priority)

Pre-defined code snippets for common patterns:

- Module `config.xml` structure
- Observer pattern
- Model/Resource Model/Collection trio
- Admin controller with ACL
- Block with template
- Helper with translation method

**Business Value:** Faster coding, consistent patterns across team

---

## 3. Technical Requirements

### 3.1 Development Environment

#### Required

- **IntelliJ IDEA Ultimate Edition** (Community Edition won't work - PHP plugin incompatible)
- **PHP plugin** for IntelliJ IDEA
- **Java 17 JDK** (for compilation)
- **Gradle 8.x or higher**

#### Recommended

- **Kotlin 2.0+** (for modern plugin development)
- **Git** for version control
- **PhpStorm 2024.3+** for testing

### 3.2 IntelliJ Platform SDK

#### Core Dependencies

- IntelliJ Platform Gradle Plugin 2.x (preferred over 1.x)
- IntelliJ Platform SDK 2024.3+
- PHP plugin (bundled dependency: `com.jetbrains.php`)
- XML plugin (bundled)
- Platform PSI (Program Structure Interface)

#### Build System Configuration

```gradle
plugins {
    id("org.jetbrains.intellij.platform") version "2.x.x"
    id("org.jetbrains.kotlin.jvm") version "2.0.0"
}

dependencies {
    intellijPlatform {
        phpstorm("2024.3")
        bundledPlugin("com.jetbrains.php")
        bundledPlugin("com.intellij.java")
    }
}
```

### 3.3 Key Technologies

- **Programming Language:** Kotlin 2.0+ (recommended) or Java 17+
- **Build Tool:** Gradle with IntelliJ Platform Gradle Plugin
- **Testing:** JUnit 5 for plugin tests
- **XML Processing:** Built-in IntelliJ XML PSI
- **PHP AST:** PhpStorm's PHP PSI API

### 3.4 Version Compatibility

#### PhpStorm Support

- **Minimum:** PhpStorm 2024.1
- **Recommended:** PhpStorm 2024.3+
- **Testing:** Test across all supported versions

#### Kotlin/Java Compatibility

- **Java Target:** 17 (matches PhpStorm 2024.x requirement)
- **Kotlin Target:** JVM 17
- **Kotlin Language Version:** 2.0+

---

## 4. Architecture Design

### 4.1 Plugin Structure

```
maho-phpstorm-plugin/
├── src/
│   ├── main/
│   │   ├── kotlin/
│   │   │   └── com/mahocommerce/phpstorm/
│   │   │       ├── navigation/
│   │   │       │   ├── XmlToPhpLineMarkerProvider.kt
│   │   │       │   ├── PhpToXmlLineMarkerProvider.kt
│   │   │       │   ├── LayoutTemplateNavigator.kt
│   │   │       │   └── RouteNavigator.kt
│   │   │       ├── completion/
│   │   │       │   ├── FactoryMethodCompletionContributor.kt
│   │   │       │   ├── EventNameCompletionContributor.kt
│   │   │       │   ├── LayoutXmlCompletionContributor.kt
│   │   │       │   └── ConfigPathCompletionContributor.kt
│   │   │       ├── generation/
│   │   │       │   ├── ModuleGenerator.kt
│   │   │       │   ├── ClassGenerator.kt
│   │   │       │   ├── ObserverGenerator.kt
│   │   │       │   └── TestGenerator.kt
│   │   │       ├── inspection/
│   │   │       │   ├── DeprecatedZendClassInspection.kt
│   │   │       │   ├── VarienToMahoInspection.kt
│   │   │       │   ├── ObserverSignatureInspection.kt
│   │   │       │   └── StrictTypesInspection.kt
│   │   │       ├── refactoring/
│   │   │       │   ├── VarienToMahoQuickFix.kt
│   │   │       │   └── ModernizationQuickFixes.kt
│   │   │       ├── index/
│   │   │       │   ├── MahoModuleIndex.kt
│   │   │       │   ├── MahoClassIndex.kt
│   │   │       │   ├── MahoEventIndex.kt
│   │   │       │   └── MahoLayoutIndex.kt
│   │   │       ├── util/
│   │   │       │   ├── MahoProjectDetector.kt
│   │   │       │   ├── XmlUtils.kt
│   │   │       │   └── PhpUtils.kt
│   │   │       └── MahoBundle.kt
│   │   └── resources/
│   │       ├── META-INF/
│   │       │   └── plugin.xml
│   │       └── messages/
│   │           └── MahoBundle.properties
│   └── test/
│       └── kotlin/
│           └── com/mahocommerce/phpstorm/
│               ├── NavigationTest.kt
│               ├── CompletionTest.kt
│               └── InspectionTest.kt
├── build.gradle.kts
├── gradle.properties
├── settings.gradle.kts
├── LICENSE (OSL-3.0)
└── README.md
```

### 4.2 Key Components

#### Indexes (Performance Critical)

**Purpose:** Fast lookups without parsing all files every time

- **MahoModuleIndex:** All modules from `app/etc/modules/*.xml`
- **MahoClassIndex:** Model/Block/Helper class mappings from `config.xml`
- **MahoEventIndex:** All events dispatched and observed
- **MahoLayoutIndex:** Layout handles, blocks, templates

**Implementation:**
- File-based indexes using IntelliJ's indexing API
- Update incrementally on file changes
- Cache results for performance

#### PSI (Program Structure Interface)

**Purpose:** Parse and navigate code structure

- Parse PHP and XML AST
- Extract class names, method names, event names
- Build reference chains between files
- Provide navigation targets

#### Line Markers

**Purpose:** Visual navigation in editor gutter

- Bidirectional navigation icons
- Show relationships between files
- Click to jump to related code
- Example: Observer class → event registration in XML

#### Completion Contributors

**Purpose:** Intelligent code completion

- Hook into PhpStorm's completion system
- Provide context-aware suggestions
- Show inline documentation
- Filter based on context

#### Inspections & Quick Fixes

**Purpose:** Code quality and automated fixes

- Analyze code for issues
- Highlight problems in editor
- Provide one-click fixes
- Enforce best practices

### 4.3 Data Flow

```
User Types Code
     ↓
Completion Contributor Triggered
     ↓
Query Indexes for Suggestions
     ↓
Present Completion Items
     ↓
User Selects → Insert Code

---

User Clicks Gutter Icon
     ↓
Line Marker Provider
     ↓
Query PSI for Navigation Target
     ↓
Navigate to Target File/Location
```

### 4.4 Performance Considerations

1. **Lazy Loading:** Only index when project is detected as Maho
2. **Incremental Updates:** Update indexes on file changes, not full rebuild
3. **Caching:** Cache expensive computations (XML parsing, PSI traversal)
4. **Background Tasks:** Run indexing in background threads
5. **Smart Invalidation:** Only invalidate affected indexes on change

---

## 5. Implementation Plan

### Phase 1: Foundation (Weeks 1-3)

#### Goals

- Set up development environment
- Create basic plugin structure
- Implement Maho project detection
- Build core indexes (modules, classes)

#### Tasks

1. **Development Environment Setup**
   - Install IntelliJ IDEA Ultimate with Gradle and Kotlin
   - Configure JDK 17
   - Set up plugin development workspace

2. **Project Scaffold**
   - Create `plugin.xml` with basic metadata
   - Set up Gradle build configuration
   - Configure plugin dependencies (PHP, XML)

3. **Maho Project Detection**
   - Implement `MahoProjectDetector`
   - Detect presence of `Mage.php`, `app/etc/modules/`, `app/code/`
   - Add project icon/badge when Maho is detected

4. **Core Indexing**
   - Build `MahoModuleIndex` - index all modules from `app/etc/modules/*.xml`
   - Build `MahoClassIndex` - index model/block/helper class mappings from `config.xml`
   - Implement incremental index updates

5. **Testing Infrastructure**
   - Set up JUnit 5 test framework
   - Add unit tests for indexing
   - Create test fixtures (sample Maho project structure)

#### Deliverables

- ✅ Working plugin skeleton installable in PhpStorm
- ✅ Accurate detection of Maho projects
- ✅ Fast, cached indexes of modules and classes
- ✅ Unit tests with >80% coverage

#### Acceptance Criteria

- Plugin loads without errors in PhpStorm 2024.3
- Detects Maho project within 2 seconds
- Indexes complete within 5 seconds for medium project (50 modules)

---

### Phase 2: Navigation (Weeks 4-6)

#### Goals

- Implement core navigation features
- Line markers for XML ↔ PHP navigation
- Template navigation

#### Tasks

1. **Factory Method Navigation**
   - Implement completion for `Mage::getModel('catalog/product')` → Navigate to class
   - Support for `getHelper()`, `getSingleton()`, `getResourceModel()`
   - Show class FQN in popup
   - Cmd/Ctrl+Click navigation

2. **XML to PHP Line Markers**
   - `config.xml` models → Model classes
   - `config.xml` blocks → Block classes
   - `config.xml` helpers → Helper classes
   - Observer registrations → Observer classes
   - Gutter icons with tooltips

3. **PHP to XML Line Markers**
   - Show gutter icons on classes referenced in XML
   - Click to jump to XML definitions
   - Show all XML references (may be multiple)

4. **Template Navigation**
   - Navigate from `Block::_construct()` template assignments to `.phtml`
   - Navigate from layout XML `<action method="setTemplate">` to `.phtml`
   - Navigate from `.phtml` back to Block classes (reverse lookup)
   - Support multiple themes

5. **Route Navigation**
   - Navigate from controller `frontName` to `controllers/`
   - URL → Controller action mapping
   - Show available routes in project

#### Deliverables

- ✅ Full bidirectional navigation between XML and PHP
- ✅ Template navigation working across themes
- ✅ Line markers visible in gutter with proper icons

#### Acceptance Criteria

- All navigation features work with Cmd/Ctrl+Click
- Gutter icons appear within 1 second of file open
- Navigation resolves to correct target >95% of time

---

### Phase 3: Code Completion (Weeks 7-9)

#### Goals

- Intelligent autocomplete for Maho-specific patterns
- Event name completion
- Configuration path completion

#### Tasks

1. **Factory Method Completion**
   - Autocomplete class names in `Mage::getModel('')`
   - Format: `module/class` based on `config.xml` mappings
   - Show inline documentation with class FQN
   - Prioritize commonly used classes

2. **Event Name Completion**
   - Index all events from `Mage::dispatchEvent()` calls across codebase
   - Autocomplete in observer registration XML
   - Autocomplete in PHP `dispatchEvent` calls
   - Show where event is dispatched and observers listening
   - Display event parameters in documentation popup

3. **Layout XML Completion**
   - Block type autocomplete (from indexed blocks)
   - Template path autocomplete (scan `app/design/`)
   - Handle name autocomplete (from layout XML files)
   - Action method name autocomplete based on Block class
   - Attribute value completion (type, name, etc.)

4. **Config Path Completion**
   - Index `system.xml` configuration paths
   - Autocomplete in `Mage::getStoreConfig('')`
   - Show default value and scope in documentation
   - Support nested paths with `/` separator

#### Deliverables

- ✅ Smart autocomplete for all major Maho patterns
- ✅ Reduced typos and faster coding
- ✅ Inline documentation during completion

#### Acceptance Criteria

- Completion appears within 200ms of typing
- Suggestions are context-aware and relevant
- Documentation popup shows useful info

---

### Phase 4: Code Generation (Weeks 10-12)

#### Goals

- Right-click context menus for generating code
- Wizard-style generators for complex structures

#### Tasks

1. **Module Generator**
   - New Module wizard with fields: name, namespace, codepool, version
   - Generate `app/etc/modules/Namespace_Module.xml`
   - Generate `app/code/{codepool}/Namespace/Module/` structure
   - Generate basic `config.xml` with module version
   - Add proper file headers with Maho copyright

2. **Class Generators**
   - **Model Generator:** With optional resource model
   - **Block Generator:** With optional template
   - **Helper Generator:** With `__()` translation method
   - **Admin Controller Generator:** With `ADMIN_RESOURCE` constant
   - **Frontend Controller Generator:** With route configuration
   - All generators include `declare(strict_types=1)` and proper headers

3. **Observer Generator (Context-Aware)**
   - Right-click on event name in XML or PHP
   - "Generate Observer" action
   - Dialog: module, area, observer name
   - Creates Observer class with proper signature:
     ```php
     public function execute(Maho\Event\Observer $observer): void
     ```
   - Registers in `config.xml`

4. **Test Generator**
   - Right-click on class → "Generate Test"
   - Generate Pest PHP test from class
   - Detect context (Frontend/Backend/Install) based on class location
   - Generate basic test structure with `uses()` and sample test

#### Deliverables

- ✅ Fast module scaffolding (< 5 seconds)
- ✅ Context-aware code generation
- ✅ Proper file headers with Maho copyright
- ✅ All generated code follows best practices

#### Acceptance Criteria

- Generators create valid, runnable code
- Generated code passes PHPStan level 6
- File structure matches Maho conventions

---

### Phase 5: Inspections & Quick Fixes (Weeks 13-15)

#### Goals

- Detect anti-patterns and deprecated code
- Provide automated fixes

#### Tasks

1. **Deprecated Code Inspections**
   - Detect `Zend_*` class usage → **ERROR** (suggest alternative)
   - Detect `Varien_*` in new files → **WARNING** (quick-fix to `Maho\`)
   - Detect `Varien_Date`/`Zend_Date` → **ERROR** (suggest `DateTime`)
   - Detect `Zend_Json` → **WARNING** (suggest `Mage::helper('core')`)
   - Detect `Zend_Http_Client` → **WARNING** (suggest Symfony HttpClient)
   - Configurable severity levels

2. **Best Practice Inspections**
   - Missing `declare(strict_types=1)` in new files → **WARNING**
   - Missing `#[\Override]` attribute on overridden methods → **INFO**
   - Admin controller missing `ADMIN_RESOURCE` constant → **ERROR**
   - Using `getParam()` instead of `getUserParam()` in admin → **WARNING**
   - Public methods without type hints → **WARNING**

3. **Quick Fixes**
   - Auto-convert `Varien_Object` → `Maho\DataObject`
   - Auto-convert all `Varien_*` to `Maho\*` equivalents
   - Auto-add `strict_types` declaration at file top
   - Auto-add `#[\Override]` to overridden methods
   - Auto-convert `Zend_Json::encode()` → `Mage::helper('core')->jsonEncode()`
   - Batch fix: Apply quick-fix to all files in directory

4. **XML Validations**
   - Validate referenced classes exist
   - Validate referenced templates exist
   - Validate observer method signatures match expected pattern
   - Validate layout XML structure (valid handles, blocks)
   - Validate `config.xml` structure against schema

#### Deliverables

- ✅ Proactive error detection
- ✅ One-click fixes for common issues
- ✅ Guidance toward Maho best practices
- ✅ Reduced runtime errors

#### Acceptance Criteria

- Inspections run without slowing down editor (< 100ms)
- False positive rate < 5%
- Quick fixes produce valid code 100% of time

---

### Phase 6: Advanced Features (Weeks 16-18)

#### Goals

- Refactoring support
- Live templates
- Polish and optimization

#### Tasks

1. **Rename Refactoring**
   - Rename Model class → Update `config.xml` + PHP references
   - Rename Helper → Update all `Mage::helper()` calls
   - Rename Block → Update layout XML + PHP references
   - Rename event → Update all observers and dispatches
   - Show preview of changes before applying

2. **Live Templates**
   - `maho-observer` → Full observer pattern (class + XML)
   - `maho-model` → Model/Resource Model/Collection trio
   - `maho-block` → Block with template
   - `maho-helper` → Helper with `__()` method
   - `maho-test` → Pest test structure
   - `maho-controller-admin` → Admin controller with ACL
   - Customizable variables in templates

3. **Performance Optimization**
   - Optimize index updates (incremental, not full rebuild)
   - Cache expensive operations (XML parsing, PSI traversal)
   - Lazy loading for large projects (> 100 modules)
   - Profile with YourKit/JProfiler and optimize hotspots
   - Memory usage optimization

4. **Documentation**
   - User guide (Markdown)
   - Developer documentation for contributors
   - Video tutorials (screencast)
   - FAQ section
   - Changelog

#### Deliverables

- ✅ Powerful refactoring capabilities
- ✅ Fast code snippet insertion
- ✅ Optimized for large projects
- ✅ Comprehensive documentation

#### Acceptance Criteria

- Rename refactoring updates all references accurately
- Plugin uses < 200MB memory for medium project
- Documentation covers all features

---

### Phase 7: Testing & Release (Weeks 19-20)

#### Goals

- Comprehensive testing
- Beta release to community
- Gather feedback

#### Tasks

1. **Testing**
   - Unit tests for all major components (>80% coverage)
   - Integration tests with real Maho projects
   - Performance benchmarking (index time, memory usage)
   - Cross-version testing (PhpStorm 2024.1, 2024.2, 2024.3)
   - Edge case testing (large projects, corrupted XML, etc.)

2. **Beta Release**
   - Publish to JetBrains Marketplace (beta channel)
   - Announce to Maho community (Discord, GitHub Discussions)
   - Create feedback channels (GitHub Issues, Discord)
   - Monitor error reports (plugin telemetry if opted-in)

3. **Bug Fixes**
   - Address beta feedback within 48 hours
   - Performance improvements based on telemetry
   - Edge case handling
   - UI/UX polish

4. **Stable Release**
   - Version 1.0.0
   - Marketing materials (screenshots, video)
   - Blog post / announcement
   - Submit to JetBrains for "Featured Plugin" consideration

#### Deliverables

- ✅ Production-ready plugin
- ✅ Published on JetBrains Marketplace
- ✅ Active community engagement
- ✅ Marketing materials

#### Acceptance Criteria

- Zero critical bugs in stable release
- 4.5+ star rating on Marketplace
- 100+ active installations within first month

---

## 6. Development Tools & Stack

### 6.1 Required Tools

| Tool | Version | Purpose |
|------|---------|---------|
| **IntelliJ IDEA Ultimate** | 2024.3+ | Development environment (required for PHP plugin) |
| **JDK** | 17+ | Java compilation and runtime |
| **Kotlin** | 2.0+ | Primary programming language |
| **Gradle** | 8.x+ | Build system and dependency management |
| **Git** | Latest | Version control |
| **PhpStorm** | 2024.3+ | Testing target |

### 6.2 Dependencies

```kotlin
// build.gradle.kts
plugins {
    id("org.jetbrains.intellij.platform") version "2.1.0"
    id("org.jetbrains.kotlin.jvm") version "2.0.0"
}

repositories {
    mavenCentral()
    intellijPlatform {
        defaultRepositories()
    }
}

dependencies {
    intellijPlatform {
        phpstorm("2024.3")
        bundledPlugin("com.jetbrains.php")
        bundledPlugin("com.intellij.java")
        bundledPlugin("com.intellij.properties")
    }

    testImplementation("org.junit.jupiter:junit-jupiter:5.10.0")
    testImplementation("org.assertj:assertj-core:3.24.2")
    testImplementation("org.mockito.kotlin:mockito-kotlin:5.1.0")
}

kotlin {
    jvmToolchain(17)
}
```

### 6.3 Plugin Configuration

```xml
<!-- plugin.xml -->
<idea-plugin>
    <id>com.mahocommerce.phpstorm</id>
    <name>Maho Support</name>
    <version>1.0.0</version>
    <vendor email="support@mahocommerce.com" url="https://mahocommerce.com">Maho</vendor>

    <description><![CDATA[
        PhpStorm plugin for Maho ecommerce platform development.

        Features:
        - Smart navigation between XML and PHP
        - Code completion for Maho patterns
        - Code generation wizards
        - Inspections and quick fixes
        - Refactoring support
    ]]></description>

    <depends>com.jetbrains.php</depends>
    <depends>com.intellij.modules.platform</depends>

    <extensions defaultExtensionNs="com.intellij">
        <!-- Line Markers -->
        <codeInsight.lineMarkerProvider
            language="PHP"
            implementationClass="com.mahocommerce.phpstorm.navigation.PhpToXmlLineMarkerProvider"/>

        <!-- Completion -->
        <completion.contributor
            language="PHP"
            implementationClass="com.mahocommerce.phpstorm.completion.FactoryMethodCompletionContributor"/>

        <!-- Inspections -->
        <localInspection
            language="PHP"
            groupName="Maho"
            displayName="Deprecated Zend class usage"
            enabledByDefault="true"
            level="ERROR"
            implementationClass="com.mahocommerce.phpstorm.inspection.DeprecatedZendClassInspection"/>
    </extensions>
</idea-plugin>
```

### 6.4 Resources

#### Official Documentation

- **IntelliJ Platform SDK:** https://plugins.jetbrains.com/docs/intellij/
- **PhpStorm Plugin Development:** https://plugins.jetbrains.com/docs/intellij/phpstorm.html
- **PHP PSI API:** https://plugins.jetbrains.com/docs/intellij/php-open-api.html
- **Plugin Development Forum:** https://intellij-support.jetbrains.com/hc/en-us/community/topics/200366979-IntelliJ-IDEA-Open-API-and-Plugin-Development

#### Reference Projects

- **Magento 2 PhpStorm Plugin:** https://github.com/magento/magento2-phpstorm-plugin
- **Symfony Plugin:** https://github.com/Haehnchen/idea-php-symfony2-plugin
- **Laravel Plugin:** https://github.com/Haehnchen/idea-php-laravel-plugin
- **Yii2 Support:** https://github.com/nvlad/yii2support

#### Learning Resources

- **IntelliJ Platform SDK DevGuide:** https://plugins.jetbrains.com/docs/intellij/welcome.html
- **JetBrains Plugin Development YouTube:** https://www.youtube.com/c/JetBrainsTV
- **IntelliJ Platform Explorer:** https://plugins.jetbrains.com/intellij-platform-explorer

---

## 7. Priority Matrix

### Must Have (v1.0) - Core Features

| Feature | Priority | Complexity | Value |
|---------|----------|------------|-------|
| ✅ Maho project detection | P0 | Low | High |
| ✅ Factory method navigation (getModel, getHelper, etc.) | P0 | Medium | High |
| ✅ XML ↔ PHP line markers | P0 | Medium | High |
| ✅ Template navigation | P0 | Medium | High |
| ✅ Factory method completion | P0 | Medium | High |
| ✅ Event name completion | P0 | Medium | High |
| ✅ Module generator | P0 | Medium | High |
| ✅ Observer generator | P0 | Medium | High |
| ✅ Deprecated Zend class inspection | P0 | Low | High |
| ✅ Varien → Maho quick-fix | P0 | Low | High |

**Total Estimated Effort:** 120-150 hours

---

### Should Have (v1.1) - Enhanced Features

| Feature | Priority | Complexity | Value |
|---------|----------|------------|-------|
| ⭐ Layout XML completion | P1 | Medium | Medium |
| ⭐ Config path completion | P1 | Medium | Medium |
| ⭐ Class generators (Model, Block, Helper, Controller) | P1 | Medium | High |
| ⭐ Test generator | P1 | Low | Medium |
| ⭐ XML validations | P1 | Medium | High |
| ⭐ Best practice inspections | P1 | Low | Medium |
| ⭐ Rename refactoring | P1 | High | High |

**Total Estimated Effort:** 100-120 hours

---

### Nice to Have (v1.2+) - Advanced Features

| Feature | Priority | Complexity | Value |
|---------|----------|------------|-------|
| 💡 Live templates | P2 | Low | Low |
| 💡 Advanced refactoring (extract observer, etc.) | P2 | High | Medium |
| 💡 Code quality suggestions | P2 | Medium | Low |
| 💡 Performance profiling integration | P2 | High | Low |
| 💡 Integration with `./maho` CLI | P2 | Low | Medium |
| 💡 PHPStan integration | P2 | Medium | Medium |
| 💡 Automatic module dependency graph | P2 | High | Low |

**Total Estimated Effort:** 80-100 hours

---

## 8. Success Metrics

### Developer Productivity

- **Navigation Efficiency:** 50-70% reduction in time to navigate between XML and PHP
- **Error Reduction:** 70% reduction in typos for event names and class references
- **Scaffolding Speed:** 80% faster module scaffolding (5 min → 1 min)
- **Code Quality:** 60% reduction in XML configuration errors

### Adoption Metrics

- **Active Installations:** 500+ within 6 months
- **Rating:** 4.5+ stars on JetBrains Marketplace
- **Community Contributions:** 5+ external contributors
- **Issue Resolution:** 90% of issues resolved within 2 weeks

### Technical Metrics

- **Performance:** Index time < 10 seconds for large projects (100+ modules)
- **Memory Usage:** < 200MB overhead
- **Accuracy:** Navigation resolves correctly >95% of time
- **Test Coverage:** >80% code coverage

### Community Engagement

- **Documentation:** Complete user guide and developer docs
- **Support:** Active Discord/GitHub Discussions
- **Updates:** Monthly releases with new features/fixes
- **Feedback Loop:** Quarterly user surveys

---

## 9. Risks & Mitigation

### Technical Risks

| Risk | Impact | Probability | Mitigation Strategy |
|------|--------|-------------|---------------------|
| **IntelliJ API changes breaking compatibility** | High | Medium | Pin to specific SDK version, follow migration guides, test with EAP releases |
| **Performance issues with large projects** | Medium | High | Implement efficient indexing, caching, lazy loading; profile early and often |
| **PSI API complexity** | Medium | Medium | Study reference plugins, use JetBrains support forums, allocate learning time |
| **Maho architecture changes** | Medium | Low | Regular updates, community feedback loop, flexible design |

### Adoption Risks

| Risk | Impact | Probability | Mitigation Strategy |
|------|--------|-------------|---------------------|
| **Limited user adoption** | Low | Medium | Marketing, documentation, community engagement, free and open-source |
| **Competing plugins** | Low | Low | Focus on Maho-specific features, better UX, active maintenance |
| **Lack of contributors** | Medium | Medium | Good documentation, welcoming community, bounties for features |

### Resource Risks

| Risk | Impact | Probability | Mitigation Strategy |
|------|--------|-------------|---------------------|
| **Developer availability** | High | Low | Realistic timeline, MVP approach, community contributors |
| **Budget constraints** | Low | Low | Minimal costs (only dev time), open-source model |
| **Maintenance burden** | Medium | Medium | Automated tests, CI/CD, community moderation |

### Compatibility Risks

| Risk | Impact | Probability | Mitigation Strategy |
|------|--------|-------------|---------------------|
| **PhpStorm version incompatibility** | Medium | Medium | Test across versions, define min/max supported versions, use compatible APIs |
| **PHP 8.3+ language changes** | Low | Low | Follow PHP roadmap, update plugin accordingly |
| **Doctrine DBAL changes** | Low | Low | Monitor Doctrine releases, update as needed |

---

## 10. Next Steps

### Immediate Actions (Week 1)

1. **Environment Setup**
   - [ ] Install IntelliJ IDEA Ultimate Edition
   - [ ] Install JDK 17
   - [ ] Set up Gradle project
   - [ ] Clone IntelliJ Platform Plugin Template: https://github.com/JetBrains/intellij-platform-plugin-template

2. **Project Initialization**
   - [ ] Create GitHub repository: `maho-phpstorm-plugin`
   - [ ] Set up basic `plugin.xml`
   - [ ] Configure Gradle build with PhpStorm dependency
   - [ ] Add OSL-3.0 license

3. **Proof of Concept**
   - [ ] Implement `MahoProjectDetector`
   - [ ] Create simple index for modules
   - [ ] Build one feature: `Mage::getModel()` navigation
   - [ ] Test in PhpStorm

### Short Term (Weeks 2-4)

1. **Core Infrastructure**
   - [ ] Complete Phase 1 (Foundation)
   - [ ] Implement all core indexes
   - [ ] Add comprehensive tests
   - [ ] Set up CI/CD with GitHub Actions

2. **Community Engagement**
   - [ ] Share POC with Maho community
   - [ ] Gather feature requests
   - [ ] Recruit beta testers
   - [ ] Create project roadmap

### Medium Term (Weeks 5-12)

1. **Feature Development**
   - [ ] Complete Phase 2 (Navigation)
   - [ ] Complete Phase 3 (Completion)
   - [ ] Complete Phase 4 (Code Generation)
   - [ ] Weekly progress updates to community

2. **Quality Assurance**
   - [ ] Add integration tests
   - [ ] Performance testing
   - [ ] User testing with beta group

### Long Term (Weeks 13-20)

1. **Polish & Release**
   - [ ] Complete Phase 5 (Inspections)
   - [ ] Complete Phase 6 (Advanced Features)
   - [ ] Complete Phase 7 (Testing & Release)
   - [ ] Publish to JetBrains Marketplace

2. **Post-Launch**
   - [ ] Monitor user feedback
   - [ ] Regular updates and bug fixes
   - [ ] Plan v1.1 features
   - [ ] Build contributor community

---

## 11. Development Workflow

### Version Control

**Branching Strategy:**
- `main` - Stable releases only
- `develop` - Active development
- `feature/*` - Feature branches
- `bugfix/*` - Bug fix branches
- `release/*` - Release preparation

**Commit Conventions:**
- Use conventional commits: `feat:`, `fix:`, `docs:`, `test:`, etc.
- Reference issues: `feat: Add observer generator (#123)`
- Keep commits focused and atomic

### CI/CD Pipeline

```yaml
# .github/workflows/build.yml
name: Build
on: [push, pull_request]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-java@v3
        with:
          java-version: '17'
      - name: Build Plugin
        run: ./gradlew buildPlugin
      - name: Run Tests
        run: ./gradlew test
      - name: Verify Plugin
        run: ./gradlew verifyPlugin
```

### Release Process

1. **Version Bump:** Update version in `gradle.properties`
2. **Changelog:** Update `CHANGELOG.md`
3. **Build:** `./gradlew buildPlugin`
4. **Test:** `./gradlew test verifyPlugin`
5. **Tag:** `git tag v1.0.0`
6. **Publish:** Upload to JetBrains Marketplace
7. **Announce:** Community channels

---

## 12. Contributing Guidelines

### How to Contribute

1. **Fork** the repository
2. **Create** a feature branch
3. **Write** tests for new features
4. **Ensure** all tests pass
5. **Submit** pull request with description

### Code Standards

- **Language:** Kotlin 2.0+
- **Style:** Follow Kotlin coding conventions
- **Testing:** Minimum 80% coverage for new code
- **Documentation:** KDoc for public APIs

### Pull Request Template

```markdown
## Description
[Describe the changes]

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Added unit tests
- [ ] Added integration tests
- [ ] Manual testing completed

## Checklist
- [ ] Code follows style guidelines
- [ ] Tests pass locally
- [ ] Documentation updated
```

---

## 13. Licensing & Distribution

### License

**Open Software License (OSL) 3.0** - Same as Maho core

- Allows commercial use
- Requires source disclosure
- Patent grant included
- Compatible with Maho ecosystem

### Distribution Channels

1. **JetBrains Marketplace** (Primary)
   - Official plugin repository
   - Automatic updates
   - User reviews and ratings

2. **GitHub Releases** (Secondary)
   - Direct download for manual installation
   - Release notes and changelog
   - Source code access

3. **Maho Website** (Tertiary)
   - Featured on Maho developer resources
   - Installation instructions
   - Link to Marketplace

---

## 14. Conclusion

The Maho PhpStorm Plugin represents a significant opportunity to enhance developer productivity and adoption of the Maho platform. By providing intelligent IDE support tailored to Maho's modern architecture, we can:

- **Reduce development time** by 30-50%
- **Improve code quality** through inspections and validations
- **Lower barrier to entry** for new Maho developers
- **Strengthen community** through open-source collaboration

With a well-defined 20-week roadmap, clear technical architecture, and proven reference implementations, this project is positioned for success.

**Next Step:** Set up development environment and begin Phase 1 implementation.

---

## Appendix A: Example Code Snippets

### Maho Project Detection

```kotlin
package com.mahocommerce.phpstorm.util

import com.intellij.openapi.project.Project
import com.intellij.openapi.vfs.VirtualFile

object MahoProjectDetector {

    fun isMahoProject(project: Project): Boolean {
        val baseDir = project.baseDir ?: return false

        return hasMagePhp(baseDir) &&
               hasAppEtcModules(baseDir) &&
               hasAppCodeCore(baseDir)
    }

    private fun hasMagePhp(baseDir: VirtualFile): Boolean {
        return baseDir.findChild("Mage.php") != null
    }

    private fun hasAppEtcModules(baseDir: VirtualFile): Boolean {
        val app = baseDir.findChild("app") ?: return false
        val etc = app.findChild("etc") ?: return false
        val modules = etc.findChild("modules") ?: return false
        return modules.isDirectory
    }

    private fun hasAppCodeCore(baseDir: VirtualFile): Boolean {
        val app = baseDir.findChild("app") ?: return false
        val code = app.findChild("code") ?: return false
        val core = code.findChild("core") ?: return false
        return core.isDirectory
    }
}
```

### Factory Method Completion

```kotlin
package com.mahocommerce.phpstorm.completion

import com.intellij.codeInsight.completion.*
import com.intellij.codeInsight.lookup.LookupElementBuilder
import com.intellij.patterns.PlatformPatterns
import com.intellij.util.ProcessingContext
import com.jetbrains.php.lang.psi.elements.MethodReference

class FactoryMethodCompletionContributor : CompletionContributor() {

    init {
        extend(
            CompletionType.BASIC,
            PlatformPatterns.psiElement(),
            FactoryMethodCompletionProvider()
        )
    }
}

class FactoryMethodCompletionProvider : CompletionProvider<CompletionParameters>() {

    override fun addCompletions(
        parameters: CompletionParameters,
        context: ProcessingContext,
        result: CompletionResultSet
    ) {
        val element = parameters.position.parent?.parent as? MethodReference ?: return

        if (element.name == "getModel") {
            val classIndex = MahoClassIndex.getInstance(parameters.position.project)
            classIndex.getAllModels().forEach { (alias, className) ->
                result.addElement(
                    LookupElementBuilder.create(alias)
                        .withTypeText(className)
                        .withIcon(PhpIcons.CLASS)
                )
            }
        }
    }
}
```

---

## Appendix B: Resources & References

### Official Links

- **Maho GitHub:** https://github.com/MahoCommerce/maho
- **Maho Docs:** https://docs.mahocommerce.com
- **JetBrains Marketplace:** https://plugins.jetbrains.com

### Technical Documentation

- **IntelliJ Platform SDK:** https://plugins.jetbrains.com/docs/intellij/
- **Kotlin Documentation:** https://kotlinlang.org/docs/
- **Gradle User Manual:** https://docs.gradle.org/

### Community

- **Maho Discord:** [Link to be added]
- **GitHub Discussions:** https://github.com/MahoCommerce/maho/discussions
- **Plugin Development Forum:** https://intellij-support.jetbrains.com

---

**Document Version:** 1.0
**Last Updated:** 2025-10-20
**Status:** Planning Complete - Ready for Implementation

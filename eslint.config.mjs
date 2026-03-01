export default [
  {
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: "script",
      globals: {
        // Browser globals
        window: "readonly",
        document: "readonly",
        navigator: "readonly",
        console: "readonly",
        alert: "readonly",
        confirm: "readonly",
        setTimeout: "readonly",
        setInterval: "readonly",
        clearTimeout: "readonly",
        clearInterval: "readonly",
        location: "readonly",
        history: "readonly",
        fetch: "readonly",
        FormData: "readonly",
        URLSearchParams: "readonly",
        URL: "readonly",
        Blob: "readonly",
        File: "readonly",
        FileReader: "readonly",
        Image: "readonly",
        Event: "readonly",
        HTMLElement: "readonly",
        MutationObserver: "readonly",
        IntersectionObserver: "readonly",
        ResizeObserver: "readonly",
        requestAnimationFrame: "readonly",
        // jQuery
        "$": "readonly",
        "jQuery": "readonly",
        // Libraries used in the project
        "Swal": "readonly",
        "Chart": "readonly",
        "gsap": "readonly",
        "Dropzone": "readonly",
        "moment": "readonly",
        "FullCalendar": "readonly",
        "tinymce": "readonly",
        "DataTable": "readonly",
        "ChartDataLabels": "readonly",
        // PHP-injected variables (common patterns)
        "csrfToken": "writable",
        "baseUrl": "writable",
        "gridData": "writable",
        "columnFiltersVisible": "writable",
      }
    },
    rules: {
      // Errors
      "no-undef": "error",
      "no-unused-vars": ["warn", { "args": "none", "varsIgnorePattern": "^_" }],
      "no-dupe-keys": "error",
      "no-duplicate-case": "error",
      "no-unreachable": "error",
      "no-constant-condition": "warn",
      "no-empty": ["warn", { "allowEmptyCatch": true }],
      "no-redeclare": ["error", { "builtinGlobals": false }],
      "no-self-assign": "error",
      "no-self-compare": "error",
      "use-isnan": "error",
      "valid-typeof": "error",
      // Best practices
      "eqeqeq": ["warn", "smart"],
      "no-eval": "error",
      "no-implied-eval": "error",
      "no-new-func": "error",
      "no-debugger": "warn",
      "no-alert": "off", // We use SweetAlert2 but also native confirm sometimes
      "no-var": "off", // Legacy code uses var
      "prefer-const": "off", // Too noisy for inline PHP scripts
    }
  }
];

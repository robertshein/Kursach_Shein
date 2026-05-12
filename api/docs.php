<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>АвтоПлюс — API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; background: #fafafa; }
        .swagger-ui .topbar { background: #1a1a2e; }
        .swagger-ui .topbar .download-url-wrapper .select-label select { border: 2px solid #4f46e5; }
        .swagger-ui .topbar-wrapper .link span { display: none; }
        .swagger-ui .topbar-wrapper .link::after {
            content: 'АвтоПлюс REST API';
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
<script>
window.onload = function () {
    var specUrl = './spec.php';
    SwaggerUIBundle({
        url:          specUrl,
        dom_id:       '#swagger-ui',
        deepLinking:  true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset
        ],
        plugins: [
            SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout:            "StandaloneLayout",
        requestInterceptor: function(request) {
            request.credentials = 'include';
            return request;
        },
        tryItOutEnabled:    true,
        persistAuthorization: true,
        displayRequestDuration: true,
        defaultModelsExpandDepth: 1,
        defaultModelExpandDepth:  2
    });
};
</script>
</body>
</html>

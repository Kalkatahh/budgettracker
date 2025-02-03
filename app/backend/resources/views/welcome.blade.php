<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"> <!-- CSRF Token -->
    <title>Laravel + Vue</title>
    @vite(['resources/css/app.css', 'resources/js/app.js']) <!-- Include Vue app -->
</head>
<body>
    <div id="app"></div>
</body>
</html>

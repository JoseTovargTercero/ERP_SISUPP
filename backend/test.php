<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <h1>TEST</h1>
</body>

</html>
<script>
    async function guardarEmpleado() {
        const payload = {
            formId: "empleado_alta",
            nombre: "Juan Pérez",
            email: "juan@mail.com",
            contrasena: "123456",
            nivel: "admin",
            estado: "activo"
        };

        let res = await fetch("/backend/handlers/api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        let data = await res.json();
        console.log(data);
    }
</script>
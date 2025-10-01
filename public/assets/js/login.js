document.getElementById("formLogin").addEventListener("submit", function (e) {
  e.preventDefault();
  let email = document.getElementsByName("email")[0].value;
  let password = document.getElementsByName("password")[0].value;
  if (email.trim() === "" || password.trim() === "") {
    alert("Por favor, complete todos los campos");
    return;
  } else {
    // enviar datos por post
    fetch("backend/handlers/login/login_validate.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        email,
        password,
      }),
    }).then((res) => {
      // manda un alert con el texto imprimido desde login_validate.php
      res.text().then((text) => {
        // convierte el texto de la respuesta a un json
        response = JSON.parse(text);

        if (response.success == true || response.success == "true") {
          location.href = "public/perfil_usuario.php";
        } else if (response.success == false) {
          toast_s("error", "Error: verifique sus credenciales");
        } else {
          toast_s("error", response.msg);
        }
      });
    });
  }
});

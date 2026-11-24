# Instalación y entorno de HostalManager

## Creación inicial de la aplicación

Creé la aplicación base de Laravel 13 desde Ubuntu (WSL) con Docker Desktop en ejecución.
Usé un contenedor temporal de Composer para generar el proyecto en la carpeta `aplicacion/`
del repositorio.

```bash
cd ~/fct-hostalmanager && docker run --rm \
  --user "$(id -u):$(id -g)" \
  --volume "$PWD:/proyecto" \
  --workdir /proyecto \
  composer:2 create-project laravel/laravel aplicacion "^13.0"
```

Este paso corresponde a la creación original del proyecto. Al clonar el repositorio,
la carpeta `aplicacion/` ya está incluida. La configuración de Sail y MariaDB queda
pendiente para el siguiente paso.
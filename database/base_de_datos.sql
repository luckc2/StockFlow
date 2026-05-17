
--  StockFlow — Script completo de base de datos
--  Importar en phpMyAdmin > SQL

CREATE DATABASE IF NOT EXISTS stockflow
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE stockflow;


-- CATEGORIAS
-- Sin dependencias, se crea primero

CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(200)
) ENGINE=InnoDB;


-- PROVEEDORES
-- Sin dependencias

CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    contacto VARCHAR(100),
    telefono VARCHAR(20),
    email VARCHAR(100)
) ENGINE=InnoDB;


-- USUARIOS
-- Los que acceden al sistema (admin, vendedor, almacen)

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin','vendedor','almacen') NOT NULL DEFAULT 'vendedor',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- CLIENTES
-- Personas que compran en el negocio

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    direccion VARCHAR(200),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- PRODUCTOS
-- Depende de categorias y proveedores (FK)

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    proveedor_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    codigo VARCHAR(50) UNIQUE,
    precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    precio_venta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_actual INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 5,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
) ENGINE=InnoDB;


-- VENTAS
-- Depende de usuarios y clientes (FK)

CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cliente_id INT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    igv DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estado ENUM('pendiente','completada','anulada') NOT NULL DEFAULT 'pendiente',
    metodo_pago VARCHAR(50) DEFAULT 'efectivo',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB;


-- DETALLE_VENTA
-- Cada producto dentro de una venta
-- Depende de ventas y productos (FK)

CREATE TABLE detalle_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;


-- COMPROBANTES
-- Boleta o factura generada por cada venta
-- Depende de ventas (FK)

CREATE TABLE comprobantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    tipo ENUM('boleta','factura','ticket') NOT NULL DEFAULT 'ticket',
    numero VARCHAR(20),
    fecha_emision DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id)
) ENGINE=InnoDB;

-- CONFIGURACION
-- guarda los datos de la empresa que se usan en varios lados 
-- — principalmente en los tickets y reportes.
CREATE TABLE configuracion (
    clave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(200) NOT NULL
);





-- DATOS DE PRUEBA

-- Usuario admin por defecto
-- contraseña: admin123 (hasheada con password_hash de PHP)
INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES
('Administrador', 'admin@stockflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Vendedor Demo', 'vendedor@stockflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendedor');

-- Categorias de ejemplo
INSERT INTO categorias (nombre, descripcion) VALUES
('Electrónica', 'Equipos y accesorios electrónicos'),
('Ropa', 'Prendas de vestir'),
('Alimentos', 'Productos alimenticios'),
('Herramientas', 'Herramientas y ferretería');

-- Proveedores de ejemplo
INSERT INTO proveedores (nombre, contacto, telefono, email) VALUES
('Distribuidora Lima SAC', 'Carlos Ríos', '987654321', 'lima@dist.com'),
('Importaciones Peru EIRL', 'Ana Torres', '912345678', 'ana@importperu.com');

-- Clientes de ejemplo
INSERT INTO clientes (nombre, telefono, email, direccion) VALUES
('Cliente General', '000000000', 'general@cliente.com', 'Lima, Perú'),
('María García', '987111222', 'maria@gmail.com', 'Miraflores, Lima'),
('Juan Pérez', '912333444', 'juan@gmail.com', 'San Isidro, Lima');

-- Productos de ejemplo
INSERT INTO productos (categoria_id, proveedor_id, nombre, codigo, precio_compra, precio_venta, stock_actual, stock_minimo) VALUES
(1, 1, 'Cable USB Tipo C', 'ELEC-001', 5.00, 15.00, 50, 10),
(1, 1, 'Audífonos Bluetooth', 'ELEC-002', 25.00, 60.00, 20, 5),
(4, 2, 'Destornillador Phillips', 'HERR-001', 3.00, 8.00, 30, 8),
(3, 2, 'Cuaderno A4 x100', 'ALIM-001', 2.50, 6.00, 100, 20);

-- Configuracion ejemplo
INSERT INTO configuracion VALUES
('empresa_nombre', 'Mi Empresa SAC'),
('empresa_ruc', '20000000000'),
('empresa_direccion', 'Lima, Perú'),
('empresa_telefono', ''),
('empresa_email', ''),
('igv_porcentaje', '18'),
('moneda_simbolo', 'S/'),
('comprobante_defecto', 'ticket');


-- Crear Vista v_stock_bajo
CREATE VIEW v_stock_bajo AS
SELECT 
    p.id, p.nombre, p.codigo,
    p.stock_actual, p.stock_minimo,
    c.nombre AS categoria
FROM productos p
JOIN categorias c ON p.categoria_id = c.id
WHERE p.stock_actual <= p.stock_minimo 
  AND p.activo = 1;
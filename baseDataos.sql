CREATE DATABASE IF NOT EXISTS blogLaravel;
USE blogLaravel; 
create table usuarios(
    id_usuario int(255) auto_increment not null,
    name varchar(50) not null,
    surname varchar(100),
    rol varchar(20),
    email varchar(255) not null,
    password varchar(255) not null,
    descripcion text,
    imagen varchar(255),
    fecha_creacion datetime DEFAULT NULL,
    fecha_updt datetime DEFAULT NULL,   
    remember_tkn varchar(255),
    CONSTRAINT pk_usuarios PRIMARY KEY(id_usuario)
)ENGINE=InnoDb;

create table categorias(
    id_categoria int(255) auto_increment not null,
    name varchar(100),
    fecha_creacion datetime DEFAULT NULL,
    fecha_updt datetime DEFAULT NULL,
    CONSTRAINT pk_categorias PRIMARY KEY(id_categoria)
)ENGINE=InnoDb;

create table posts(
    id_post int(255) auto_increment not null,
    usuario int(255) not null,
    categoria int(255) not null,
    titulo varchar(255) not null,
    contenido text not null,
    imagen varchar(255),
    fecha_creacion datetime DEFAULT NULL,
    fecha_updt datetime DEFAULT NULL,
    CONSTRAINT pk_posts PRIMARY KEY(id_post),
    CONSTRAINT fk_usuario foreign key (usuario) references usuarios(id_usuario),
    CONSTRAINT fk_categoria foreign key (categoria) references categorias(id_categoria)
)ENGINE=InnoDb;

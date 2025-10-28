-- Creates the 'uporabniki' table to store user information.
CREATE TABLE uporabniki (
    id SERIAL PRIMARY KEY,
    ime VARCHAR(100) NOT NULL,
    priimek VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    telefonska_stevilka VARCHAR(20),
    geslo_hash VARCHAR(255) NOT NULL,
    vloga VARCHAR(10) NOT NULL,
    ustvarjeno_ob TIMESTAMP DEFAULT NOW()
);

-- Creates the 'tecaji' table to store course information.
CREATE TABLE tecaji (
    id SERIAL PRIMARY KEY,
    naziv VARCHAR(255) NOT NULL,
    opis TEXT,
    ustvarjeno_ob TIMESTAMP DEFAULT NOW()
);

-- Creates the 'vpisi' table as a pivot for student-course enrollments.
CREATE TABLE vpisi (
    id SERIAL PRIMARY KEY,
    id_studenta INTEGER NOT NULL,
    id_tecaja INTEGER NOT NULL,
    vpisano_ob TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (id_studenta) REFERENCES uporabniki(id) ON DELETE CASCADE,
    FOREIGN KEY (id_tecaja) REFERENCES tecaji(id) ON DELETE CASCADE
);

-- Creates the 'ucitelji_tecajev' table as a pivot for teacher-course assignments.
CREATE TABLE ucitelji_tecajev (
    id SERIAL PRIMARY KEY,
    id_ucitelja INTEGER NOT NULL,
    id_tecaja INTEGER NOT NULL,
    FOREIGN KEY (id_ucitelja) REFERENCES uporabniki(id) ON DELETE CASCADE,
    FOREIGN KEY (id_tecaja) REFERENCES tecaji(id) ON DELETE CASCADE
);

-- Creates the 'gradiva' table to store learning materials for courses.
CREATE TABLE gradiva (
    id SERIAL PRIMARY KEY,
    id_tecaja INTEGER NOT NULL,
    naslov VARCHAR(255) NOT NULL,
    tip VARCHAR(10) NOT NULL,
    vsebina TEXT NOT NULL,
    nalozeno_ob TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (id_tecaja) REFERENCES tecaji(id) ON DELETE CASCADE
);

-- Renamed from 'assignments' table to 'naloge' for consistency.
CREATE TABLE naloge (
    id SERIAL PRIMARY KEY,
    id_tecaja INTEGER NOT NULL,
    naslov VARCHAR(255) NOT NULL,
    opis TEXT,
    rok_oddaje TIMESTAMP,
    ustvarjeno_ob TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (id_tecaja) REFERENCES tecaji(id) ON DELETE CASCADE
);

-- Renamed from 'submissions' table to 'oddaje' for consistency.
CREATE TABLE oddaje (
    id SERIAL PRIMARY KEY,
    id_naloge INTEGER NOT NULL,
    id_studenta INTEGER NOT NULL,
    pot_do_datoteke VARCHAR(512) NOT NULL,
    oddano_ob TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (id_naloge) REFERENCES naloge(id) ON DELETE CASCADE,
    FOREIGN KEY (id_studenta) REFERENCES uporabniki(id) ON DELETE CASCADE
);

-- Renamed from 'grades' table to 'ocene' for consistency.
CREATE TABLE ocene (
    id SERIAL PRIMARY KEY,
    id_oddaje INTEGER NOT NULL,
    id_ucitelja INTEGER,
    ocena VARCHAR(50) NOT NULL,
    povratna_informacija TEXT,
    ocenjeno_ob TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (id_oddaje) REFERENCES oddaje(id) ON DELETE CASCADE,
    FOREIGN KEY (id_ucitelja) REFERENCES uporabniki(id) ON DELETE SET NULL
);
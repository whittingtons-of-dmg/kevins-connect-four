<?php

require_once __DIR__ . '/vendor/autoload.php';

use MediumDubb\ConnectFour\PDOConnector;

$db = new PDOConnector();

$db->run("
    DROP TABLE tokens;
");

$db->run("
    DROP TABLE boards;
");

$db->run("
    DROP TABLE players;
");

$db->run("
    CREATE TABLE IF NOT EXISTS players (
        id INT AUTO_INCREMENT PRIMARY KEY
    )
");

$db->run("
    CREATE TABLE IF NOT EXISTS boards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player1 INT NOT NULL,
        player2 INT NULL,
        current_player INT NULL,
        winner INT NULL,
        
        CONSTRAINT board_player_one 
            FOREIGN KEY (player1) REFERENCES players(id),
        
        CONSTRAINT board_player_two 
            FOREIGN KEY (player2) REFERENCES players(id),
        
        CONSTRAINT board_curr_player 
            FOREIGN KEY (current_player) REFERENCES players(id),
        
        CONSTRAINT board_winner 
            FOREIGN KEY (winner) REFERENCES players(id)
    )
");

$db->run("
    CREATE TABLE IF NOT EXISTS tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        board INT NOT NULL,
        player INT NOT NULL,
        board_column INT NOT NULL,
        board_row INT NOT NULL,
        
        CONSTRAINT token_board
            FOREIGN KEY (board) REFERENCES boards(id),

        CONSTRAINT token_player
            FOREIGN KEY (player) REFERENCES players(id),
        
        CONSTRAINT valid_column
            CHECK (board_column BETWEEN 0 AND 6),
        
        CONSTRAINT valid_row
            CHECK (board_row BETWEEN 0 AND 5)
    )
");

echo "Database setup complete.\n";
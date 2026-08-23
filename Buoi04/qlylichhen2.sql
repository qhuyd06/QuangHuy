USE quanlylichhen;
GO

CREATE TABLE feedbacks (
    id INT IDENTITY(1,1) PRIMARY KEY,
    lecturer_name NVARCHAR(100) NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment NVARCHAR(MAX) NOT NULL,
    created_at DATETIME DEFAULT GETDATE()
);
GO
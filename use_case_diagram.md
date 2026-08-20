# 📊 Use Case Diagram (UML Style)

แผนภาพ Use Case รูปแบบมาตรฐาน (มีกรอบระบบตรงกลาง และผู้ใช้งานอยู่ซ้าย-ขวา) ถูกเขียนด้วย Mermaid แบบที่อิงตามรูปตัวอย่างที่คุณส่งมาครับ

```mermaid
flowchart LR
    %% Actors on the Left
    Guest(["👤\nผู้เยี่ยมชม (Guest)"])
    Musician(["👤\nนักดนตรี (Musician)"])
    Employer(["👤\nผู้ว่าจ้าง (Employer)"])

    %% Actor on the Right
    Admin(["👤\nผู้ดูแลระบบ (Admin)"])

    %% System Boundary
    subgraph "ระบบ MY MELODY"
        direction TB
        UC1([สมัครสมาชิก / เข้าสู่ระบบ])
        UC2([ค้นหานักดนตรี / ดูโปรไฟล์])
        UC3([จัดการโปรไฟล์และผลงาน])
        UC4([ใช้งานระบบชุมชน Community])
        UC5([จัดการคำขอและสถานะจ้างงาน])
        UC6([รีวิวและให้คะแนน])
        UC7([จัดการข้อพิพาทและรายงานปัญหา])
        UC8([จัดการผู้ใช้งานและยืนยันตัวตน])
    end

    %% Connect Left Actors (ใช้เส้นตรง --- แทนลูกศร ตามหลัก UML)
    Guest --- UC1
    Guest --- UC2

    Musician --- UC1
    Musician --- UC3
    Musician --- UC4
    Musician --- UC5
    Musician --- UC7

    Employer --- UC1
    Employer --- UC2
    Employer --- UC4
    Employer --- UC5
    Employer --- UC6
    Employer --- UC7

    %% Connect Right Actor
    UC1 --- Admin
    UC7 --- Admin
    UC8 --- Admin

    %% Styling
    classDef actor fill:transparent,stroke:none,color:#000,font-weight:bold;
    class Guest,Musician,Employer,Admin actor;
    
    classDef usecase fill:#fff,stroke:#333,stroke-width:1.5px,color:#000;
    class UC1,UC2,UC3,UC4,UC5,UC6,UC7,UC8 usecase;
    
    style ระบบ MY MELODY fill:transparent,stroke:#333,stroke-width:2px,color:#000,stroke-dasharray: 5 5;
```

---
**หมายเหตุ:** 
- ในแผนภาพผมใช้ไอคอน 👤 แทนรูปคน (Stick figure) เนื่องจากรูปแบบ Mermaid ไม่รองรับรูปคนวาดเส้นแบบดั้งเดิม แต่การใช้โครงสร้างนี้จะได้ Layout ที่มี Actor อยู่ซ้ายขวา และมีกรอบ System Boundary ตรงกลาง ตรงกับหลักการเขียน UML Use Case ทุกประการครับ
- ผมได้ตัดลูกศรออก และเปลี่ยนเป็นเส้นตรง (`---`) ตามหลักการวาด Use Case Diagram มาตรฐานครับ

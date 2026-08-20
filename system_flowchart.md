# 🗺️ System Flowchart (Information Architecture)

แผนผังด้านล่างนี้ออกแบบตามรูปแบบที่คุณส่งมาครับ โดยแสดงลำดับขั้นการเข้าถึงฟีเจอร์ต่างๆ ของระบบ MY MELODY แบ่งตามบทบาทของผู้ใช้งาน (ผู้ว่าจ้าง, นักดนตรี, และแอดมิน)

```mermaid
flowchart LR
    %% กำหนดสไตล์ของกล่องให้เป็นสี่เหลี่ยมเหมือนในรูป
    classDef default fill:#fff,stroke:#333,stroke-width:1px,color:#000,shape:rect;

    Guest[ผู้เยี่ยมชม] --> Register[สมัครสมาชิก]
    Guest --> SearchGuest[ค้นหานักดนตรี]
    
    Login[เข้าสู่ระบบ] --> Logout[ออกจากระบบ]
    
    %% สายของผู้ว่าจ้าง (Employer)
    Login --> Employer[ผู้ว่าจ้าง]
    Employer --> EmpSearch[ค้นหานักดนตรี]
    EmpSearch --> EmpSearchName[ค้นหาตามชื่อ/วง]
    EmpSearch --> EmpSearchGenre[ค้นหาตามแนวเพลง]
    
    Employer --> EmpBooking[การจ้างงาน]
    EmpBooking --> BookRequest[ส่งคำขอจ้างงาน]
    EmpBooking --> BookStatus[สถานะการจ้างงาน]
    BookStatus --> StatusPending[รอการยืนยัน]
    BookStatus --> StatusConfirmed[ยืนยันแล้ว/กำลังดำเนินการ]
    BookStatus --> StatusCompleted[เสร็จสิ้นงาน]
    
    Employer --> EmpHistory[ประวัติการจ้างงาน]
    EmpHistory --> EmpReview[รีวิวและให้คะแนนนักดนตรี]
    
    Employer --> EmpCommunity[ระบบชุมชน Community]
    EmpCommunity --> EmpFeed[ดูหน้าฟีด]
    EmpCommunity --> EmpComment[กดไลก์/คอมเมนต์]

    %% สายของนักดนตรี (Musician)
    Login --> Musician[นักดนตรี]
    Musician --> MusProfile[จัดการโปรไฟล์]
    MusProfile --> MusInfo[ข้อมูลส่วนตัวและเรทราคา]
    MusProfile --> MusPort[จัดการ Portfolio]
    MusPort --> PortVideo[อัปโหลดวิดีโอ]
    MusPort --> PortAudio[อัปโหลดไฟล์เสียง]
    MusPort --> PortImage[อัปโหลดรูปภาพ]
    
    Musician --> MusBooking[จัดการงานจ้าง]
    MusBooking --> MusBookAction[กดยอมรับ/ปฏิเสธงาน]
    
    Musician --> MusCommunity[ระบบชุมชน Community]
    MusCommunity --> MusPost[โพสต์ข้อความ/รูปภาพ]
    MusCommunity --> MusPoll[สร้างโพลสำรวจ]

    %% สายของแอดมิน (Admin)
    Login --> Admin[ผู้ดูแลระบบ]
    Admin --> AdminDashboard[หน้าแดชบอร์ดสรุปผล]
    Admin --> AdminUsers[จัดการผู้ใช้งาน]
    AdminUsers --> VerifyMusician[ยืนยันตัวตนนักดนตรี]
    Admin --> AdminDispute[จัดการข้อพิพาท/แจ้งปัญหา]

```

แผนผังนี้ (Information Architecture) จะช่วยให้เห็นภาพรวมได้ทันทีว่า เมื่อผู้ใช้เข้าสู่ระบบมาแล้ว จะสามารถแยกไปใช้งานฟีเจอร์ใดได้บ้าง เหมือนกับตัวอย่างที่คุณส่งมาเลยครับ หากต้องการปรับเปลี่ยนคำไหนสามารถแจ้งได้เลยครับ

# ⏱️ แผนภาพลำดับเหตุการณ์แบบครอบคลุม (Comprehensive Sequence Diagram)

แผนภาพด้านล่างนี้แสดงลำดับเหตุการณ์แบบ **End-to-End** ของระบบ MY MELODY ตั้งแต่ผู้ใช้ทั่วไปเข้ามาสมัครสมาชิก การอัปเกรดเป็นนักดนตรี การค้นหาและจ้างงาน ไปจนถึงการใช้งานระบบชุมชน (Community) ในภาพเดียวครับ

```mermaid
sequenceDiagram
    autonumber
    
    actor G as Guest (ผู้เยี่ยมชม)
    actor E as Employer (ผู้ว่าจ้าง)
    actor M as Musician (นักดนตรี)
    participant S as My Melody System
    participant DB as Database

    %% 1. การเข้าสู่ระบบและสมัครสมาชิก
    rect rgb(230, 240, 255)
        Note over G, DB: 1. การลงทะเบียนและการเข้าสู่ระบบ (Authentication)
        G->>S: สมัครสมาชิก (register.php)
        S->>DB: INSERT INTO users (role='employer')
        DB-->>S: สมัครสำเร็จ
        S-->>G: เปลี่ยนสถานะเป็นผู้ว่าจ้าง (Employer)
        G->>S: เข้าสู่ระบบ (login.php)
        S->>DB: ตรวจสอบข้อมูลผู้ใช้ (SELECT users)
        DB-->>S: ข้อมูลถูกต้อง (สร้าง Session)
    end

    %% 2. การเป็นนักดนตรีและการจัดการผลงาน
    rect rgb(255, 240, 230)
        Note over E, DB: 2. การอัปเกรดเป็นนักดนตรี (Become Musician)
        E->>S: กรอกข้อมูลวงดนตรีและเรทราคา (become_musician.php)
        S->>DB: UPDATE users SET role='musician'
        S->>DB: INSERT INTO musician_profiles
        DB-->>S: อัปเกรดสำเร็จ
        S-->>E: เปลี่ยนสถานะเป็นนักดนตรี (Musician)
        M->>S: อัปโหลดผลงานรูป/วิดีโอ (portfolio_manager.php)
        S->>DB: INSERT INTO portfolios
        DB-->>S: บันทึกผลงานสำเร็จ
    end

    %% 3. การค้นหาและระบบจ้างงาน (Core System)
    rect rgb(230, 255, 230)
        Note over E, DB: 3. การจองคิวและจ้างงาน (Booking Flow)
        E->>S: ค้นหานักดนตรีตามแนวเพลง/พื้นที่ (search.php)
        S->>DB: SELECT จาก musician_profiles
        DB-->>S: แสดงรายการนักดนตรี
        S-->>E: เลือกนักดนตรีที่ต้องการ
        
        E->>S: ส่งคำขอจองคิวงาน (booking.php)
        S->>DB: INSERT INTO bookings (status='pending')
        DB-->>S: ส่งคำขอสำเร็จ
        
        M->>S: ตรวจสอบตารางงานและกดยอมรับ
        S->>DB: UPDATE bookings SET status='confirmed'
        DB-->>S: อัปเดตสถานะสำเร็จ
        
        Note over E, M: ถึงกำหนดวันแสดงและงานเสร็จสิ้น
        
        M->>S: กดจบงาน (completed)
        S->>DB: UPDATE bookings SET status='completed'
        
        E->>S: ให้คะแนนดาวและรีวิวนักดนตรี
        S->>DB: INSERT INTO reviews & UPDATE rating_score
        DB-->>S: บันทึกรีวิวสำเร็จ
    end

    %% 4. ระบบชุมชน (Community & Feed)
    rect rgb(255, 230, 255)
        Note over E, DB: 4. การมีส่วนร่วมในชุมชน (Community System)
        M->>S: โพสต์วิดีโอโปรโมทวง (ajax_community.php)
        S->>DB: INSERT INTO posts
        DB-->>S: โพสต์สำเร็จ
        
        E->>S: เห็นโพสต์บนหน้าฟีดและกดถูกใจ (Like)
        S->>DB: INSERT INTO post_reactions
        
        E->>S: แสดงความคิดเห็น (Comment)
        S->>DB: INSERT INTO comments
        S->>DB: INSERT INTO community_notifications (แจ้งเตือน M)
        DB-->>S: บันทึกการโต้ตอบสำเร็จ
        
        S-->>M: แสดงการแจ้งเตือนว่ามีคนถูกใจและคอมเมนต์
    end
```

### 💡 สรุปขั้นตอนการทำงาน (Flow Summary):
1. **สีฟ้า (Authentication):** ผู้เยี่ยมชมสมัครสมาชิก ระบบจะตั้งค่าเริ่มต้นให้เป็น **Employer (ผู้ว่าจ้าง)**
2. **สีส้ม (Profile Setup):** หากต้องการรับงาน ผู้ว่าจ้างสามารถมากรอกข้อมูลและอัปเกรดตัวเองเป็น **Musician (นักดนตรี)** พร้อมตั้งค่าพอร์ตโฟลิโอได้
3. **สีเขียว (Booking):** เป็นระบบหลัก (Core Flow) ที่ผู้ว่าจ้างสามารถค้นหานักดนตรี ส่งคำขอจ้างงาน นักดนตรีกดยอมรับ เมื่อจบงานผู้ว่าจ้างทำการรีวิว
4. **สีชมพู (Community):** ระบบโซเชียลที่ทุกบทบาท (Employer และ Musician) สามารถมาตั้งโพสต์ พูดคุย และกดรีแอคชันโต้ตอบกันได้ พร้อมระบบแจ้งเตือน

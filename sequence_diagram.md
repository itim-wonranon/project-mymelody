# ⏱️ Sequence Diagram: ระบบการจองคิวงานและรีวิวนักดนตรี

จากโครงสร้างโค้ดในไฟล์ `booking.php` ผมได้วิเคราะห์ขั้นตอนการทำงาน (Flow) ของระบบจ้างงานนักดนตรี ตั้งแต่เริ่มจองคิว ไปจนถึงจบงานและให้คะแนนรีวิว โดยเขียนออกมาเป็น **Sequence Diagram** (แผนภาพลำดับเหตุการณ์) ดังนี้ครับ:

```mermaid
sequenceDiagram
    autonumber
    
    actor E as Employer (ผู้ว่าจ้าง)
    participant S as System (booking.php)
    participant DB as Database
    actor M as Musician (นักดนตรี)

    %% 1. ขั้นตอนการจอง
    rect rgb(0, 0, 0, 0.05)
        Note left of E: 1. ขั้นตอนการส่งคำขอจองคิวงาน
        E->>S: กรอกข้อมูลและส่งคำขอจองคิวงาน (submit_booking)
        S->>DB: INSERT INTO bookings (status='pending')
        DB-->>S: บันทึกข้อมูลสำเร็จ
        S-->>E: แจ้งเตือน: "ส่งคำขอจองคิวงานเรียบร้อยแล้ว"
    end

    %% 2. ขั้นตอนการตอบรับ
    rect rgb(0, 0, 0, 0.05)
        Note right of M: 2. ขั้นตอนการตอบรับงาน
        M->>S: เข้าสู่หน้าตารางงาน (booking.php)
        S->>DB: SELECT bookings (ตรวจสอบงานที่รอยืนยัน)
        DB-->>S: คืนค่ารายการจอง
        S-->>M: แสดงรายการคำขอจองใหม่
        M->>S: กดยอมรับงาน (update_status: 'confirmed')
        S->>DB: UPDATE bookings SET status='confirmed'
        DB-->>S: อัปเดตข้อมูลสำเร็จ
        S-->>M: แจ้งเตือน: "อัปเดตสถานะงานเรียบร้อย"
    end

    %% 3. ขั้นตอนการจบงาน
    Note over E, M: ⏳ ถึงกำหนดวันแสดงดนตรี และการแสดงจบลง
    
    rect rgb(0, 0, 0, 0.05)
        Note right of M: 3. ขั้นตอนการจบงาน
        M->>S: กดปุ่ม จบงานสำเร็จ (update_status: 'completed')
        S->>DB: UPDATE bookings SET status='completed'
        DB-->>S: อัปเดตข้อมูลสำเร็จ
        S-->>M: แจ้งเตือน: "อัปเดตสถานะงานเรียบร้อย"
    end

    %% 4. ขั้นตอนการรีวิว
    rect rgb(0, 0, 0, 0.05)
        Note left of E: 4. ขั้นตอนการรีวิวและให้คะแนน
        E->>S: เข้าสู่หน้าตารางงาน (booking.php)
        S->>DB: SELECT bookings (ตรวจสอบงานที่ completed)
        DB-->>S: คืนค่ารายการจองที่เสร็จสิ้น
        S-->>E: แสดงปุ่ม "เขียนรีวิว"
        E->>S: ส่งคะแนนและคอมเมนต์รีวิว (submit_review)
        
        %% Database operations for review
        S->>DB: ตรวจสอบเงื่อนไขว่างานเสร็จสิ้นจริง (status='completed')
        S->>DB: ตรวจสอบว่ายังไม่ได้รีวิวซ้ำ
        S->>DB: INSERT INTO reviews (rating, comment)
        S->>DB: SELECT AVG(rating) (คำนวณคะแนนเฉลี่ยล่าสุด)
        S->>DB: UPDATE musician_profiles SET rating_score (อัปเดตคะแนนรวมนักดนตรี)
        
        DB-->>S: บันทึกรีวิวและอัปเดตคะแนนสำเร็จ
        S-->>E: แจ้งเตือน: "บันทึกรีวิวเรียบร้อย ขอบคุณครับ"
    end
```

### 💡 อธิบายเพิ่มเติม:
- **Employer (ผู้ว่าจ้าง):** เป็นจุดเริ่มต้นของ Flow โดยส่งคำขอจองคิว และเป็นผู้ปิดท้ายด้วยการรีวิว
- **Musician (นักดนตรี):** เป็นผู้ตรวจสอบคิวงาน ตอบรับ และเปลี่ยนสถานะเป็นจบงาน
- **System & Database:** ในแต่ละแอคชัน ระบบจะทำการ Query หรือ Update ข้อมูลในฐานข้อมูล (เช่น การเปลี่ยน `status` ระหว่าง pending -> confirmed -> completed) รวมถึงการคำนวณคะแนนเฉลี่ยนักดนตรีให้แบบอัตโนมัติเมื่อมีการรีวิวครับ

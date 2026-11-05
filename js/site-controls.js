(function () {
  'use strict';

  const translations = {
    'หน้าแรก': 'Home',
    'ซื้อ': 'Buy',
    'เช่า': 'Rent',
    'โครงการใหม่': 'New projects',
    'บ้านเดี่ยว': 'Houses',
    'พาณิชย์': 'Commercial',
    'ลงประกาศ': 'List a property',
    'ลงประกาศฟรี': 'List for free',
    'ลงประกาศกับเรา': 'List with us',
    'ค้นหา': 'Search',
    'ค้นหาอสังหาริมทรัพย์': 'Search properties',
    'เข้าสู่ระบบ': 'Log in',
    'สมัครสมาชิกเพื่อลงประกาศ': 'Create an account to list a property',
    'ชื่อ-นามสกุล': 'Full name',
    'เบอร์โทรศัพท์ (ไม่บังคับ)': 'Phone number (optional)',
    'LINE ID (ไม่บังคับ)': 'LINE ID (optional)',
    'ยอมรับให้จัดเก็บชื่อ อีเมล ชื่อผู้ใช้ และช่องทางติดต่อเพื่อจัดการบัญชีและประกาศ โดยเจ้าของเว็บไซต์เท่านั้นที่ดูข้อมูลบัญชีได้': 'I agree to the storage of my name, email, username, and contact details for account and listing management. Only the site owner can view account details.',
    'มีบัญชีอยู่แล้ว? เข้าสู่ระบบ': 'Already have an account? Log in',
    'บัญชีและความเป็นส่วนตัว': 'Account and privacy',
    'LINE ID': 'LINE ID',
    'ติดต่อผ่าน LINE': 'Contact via LINE',
    'เพิ่ม LINE ID เพื่อให้ผู้สนใจติดต่อได้ง่ายขึ้น': 'Add a LINE ID so interested people can contact you.',
    'นโยบายคุกกี้': 'Cookie preferences',
    'เราใช้คุกกี้ที่จำเป็นเพื่อให้เว็บไซต์และระบบเข้าสู่ระบบทำงาน ส่วนคุกกี้วิเคราะห์ผู้เข้าชมเป็นทางเลือกและจะเริ่มหลังจากคุณยินยอมเท่านั้น': 'Essential cookies keep the site and sign-in working. Optional analytics cookies are used only after you consent.',
    'ยอมรับคุกกี้วิเคราะห์': 'Accept analytics cookies',
    'ใช้เฉพาะคุกกี้ที่จำเป็น': 'Use essential cookies only',
    'บันทึกการตั้งค่าไม่สำเร็จ กรุณาลองอีกครั้ง': 'Could not save your choice. Please try again.',
    'ตั้งค่าคุกกี้': 'Cookie settings',
    'เข้าสู่ระบบหรือสมัครสมาชิกเพื่อดำเนินการลงประกาศ ระบบจะพากลับไปยังแบบฟอร์มหลังเข้าสู่ระบบ': 'Log in or create an account to list a property. You will return to the listing form afterward.',
    'ออกจากระบบ': 'Log out',
    'สมัครสมาชิก': 'Create account',
    'กลับหน้าแรก': 'Back to home',
    'กลับหน้าเว็บไซต์': 'Back to website',
    'กลับสู่พื้นที่ส่วนตัว': 'Back to dashboard',
    'ดูหน้าเว็บ': 'View listing',
    'ดูรายละเอียด': 'View details',
    'เกี่ยวกับเรา': 'About us',
    'เมนูหลัก': 'Main navigation',
    'ขั้นตอนการลงประกาศ': 'Listing steps',
    'บทความ': 'Articles',
    'ทุกประเภท': 'All property types',
    'ขายและเช่า': 'Buy and rent',
    'ให้เช่า': 'For rent',
    'ขาย': 'For sale',
    'คอนโด': 'Condo',
    'ทาวน์เฮ้าส์ / ทาวน์โฮม': 'Townhouse',
    'ทาวน์โฮม': 'Townhouse',
    'ที่ดิน': 'Land',
    'อาคารพาณิชย์': 'Commercial property',
    'โกดัง / โรงงาน': 'Warehouse / Factory',
    'อื่น ๆ': 'Other',
    'คอนโดแนะนำ': 'Featured condos',
    'บ้านแนะนำ': 'Featured houses',
    'ทาวน์เฮ้าส์แนะนำ': 'Featured townhouses',
    'ที่ดินแนะนำ': 'Featured land',
    'อาคารพาณิชย์แนะนำ': 'Featured commercial properties',
    'โกดังและโรงงาน': 'Warehouses and factories',
    'อสังหาริมทรัพย์แนะนำ': 'Featured properties',
    'พบประกาศ': 'Listings found',
    'ผลการค้นหา': 'Search results',
    'พบ 0 ประกาศ': 'No listings found',
    'ยังไม่มีประกาศในหมวดนี้': 'There are no listings in this category yet.',
    'ไม่มีรูปภาพ': 'No image available',
    'รูปภาพประกาศ': 'Property photos',
    'ประเภทอสังหาริมทรัพย์': 'Property type',
    'ประเภทอสังหาฯ': 'Property type',
    'ข้อมูลประกาศ': 'Listing information',
    'รูปแบบประกาศ': 'Listing type',
    'รายละเอียดประกาศ': 'Listing details',
    'รายละเอียดเพิ่มเติม': 'Additional details',
    'รายละเอียด': 'Details',
    'ทำเลที่ตั้ง': 'Location',
    'ทำเล': 'Location',
    'ทำเล / ที่อยู่': 'Location / address',
    'ราคา': 'Price',
    'ราคา (บาท)': 'Price (THB)',
    'ห้องนอน': 'Bedrooms',
    'ห้องน้ำ': 'Bathrooms',
    'พื้นที่ใช้สอย (ตร.ม.)': 'Usable area (sq.m.)',
    'ที่จอดรถ (คัน)': 'Parking spaces',
    'ชั้น': 'Floor',
    'เฟอร์นิเจอร์': 'Furnishing',
    'สิ่งอำนวยความสะดวก': 'Facilities',
    'สถานที่ใกล้เคียง': 'Nearby places',
    'ค้นหาตามความต้องการ': 'Browse by what you need',
    'ประกาศขายคอนโด': 'Condos for sale',
    'ประกาศขายบ้านเดี่ยว': 'Detached houses for sale',
    'ประกาศขายทาวน์โฮม': 'Townhouses for sale',
    'ประกาศขายที่ดิน': 'Land for sale',
    'บ้านให้เช่า': 'Houses for rent',
    'คอนโดให้เช่า': 'Condos for rent',
    'ภาพประจำหมวดหมู่': 'Category images',
    'บันทึกภาพ': 'Save image',
    'ลบภาพ': 'Delete image',
    'เลือกรูปภาพใหม่': 'Choose a new image',
    'อัปโหลดภาพ JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB ภาพจะแสดงส่วนหัวของหน้าหมวดหมู่': 'Upload a JPG, PNG, or WebP image up to 5 MB. It will appear at the top of the category page.',
    'บันทึกภาพประจำหมวดหมู่เรียบร้อยแล้ว': 'Category image saved.',
    'ลบภาพหมวดหมู่เรียบร้อยแล้ว': 'Category image deleted.',
    'เวลาเข้าใช้งานและสถานะผู้เข้าชม': 'Visitor timestamps and status',
    'ผู้เข้าชมใหม่ในช่วงที่เลือก': 'New visitors in selected period',
    'ผู้เข้าชมกลับมาอีก (เคยเข้าก่อนช่วงนี้)': 'Returning visitors (visited before selected period)',
    'เข้าครั้งแรก': 'First visit',
    'เข้าล่าสุด': 'Most recent visit',
    'กิจกรรมในช่วงนี้': 'Events in this period',
    'อุปกรณ์ / เบราว์เซอร์': 'Device / browser',
    'พร้อมขาย': 'For sale',
    'พร้อมให้เช่า': 'For rent',
    'ปิดประกาศแล้ว': 'Listing closed',
    'แผนที่และสถานที่ใกล้เคียง': 'Map and nearby places',
    'จัดการประกาศ': 'Manage listings',
    'ภาพหมวดหมู่': 'Category images',
    'เลือกภาพใหม่': 'Choose a new image',
    'ผู้เข้าชมใหม่': 'New visitor',
    'กลับมาอีก': 'Returning visitor',
    'หัวข้อประกาศ': 'Listing title',
    'หัวข้อประกาศ *': 'Listing title *',
    'ชื่อผู้ติดต่อ': 'Contact name',
    'เบอร์โทรศัพท์': 'Phone number',
    'อีเมล': 'Email',
    'ชื่อผู้ใช้': 'Username',
    'รหัสผ่าน': 'Password',
    'ชื่อผู้ใช้ (Username)': 'Username',
    'ชื่อผู้ใช้ หรืออีเมล': 'Username or email',
    'รหัสผ่าน (Password)': 'Password',
    'แผนที่': 'Map',
    'แผนที่จำลองเพื่อประกอบการนำเสนอ': 'Illustrative map for presentation',
    'ไม่มีพิกัดแผนที่': 'No map coordinates provided',
    'ข้อมูลยังไม่พร้อมใช้งาน': 'Information is not available yet',
    'สอบถามรายละเอียดประกาศ': 'Ask about this listing',
    'ผู้ลงประกาศ': 'Listed by',
    'ติดต่อผู้ลงประกาศโดยตรงเพื่อสอบถามข้อมูลเพิ่มเติม': 'Contact the advertiser directly for more information.',
    'มีอสังหาริมทรัพย์ต้องการลงประกาศ?': 'Have a property to list?',
    'ลงประกาศขายหรือให้เช่า': 'List a property for sale or rent',
    'ลงประกาศกับ Standard Property': 'List with Standard Property',
    'ข้อมูลอสังหาริมทรัพย์': 'Property information',
    'รายละเอียดประกาศ': 'Listing details',
    'รูปภาพและข้อมูลติดต่อ': 'Photos and contact information',
    'ข้อมูลหลักที่จะแสดงบนหน้าประกาศ': 'The main information shown on your listing',
    'บอกจุดเด่นของทรัพย์เพื่อช่วยให้ผู้สนใจตัดสินใจ': 'Highlight your property to help people make a decision.',
    'รูปภาพและเบอร์โทรจะแสดงในหน้ารายละเอียดประกาศ': 'Photos and phone number appear on the listing details page.',
    'ส่งประกาศเพื่อรอตรวจสอบ': 'Submit listing for review',
    'รับประกาศของคุณแล้ว': 'Your listing has been received',
    'ทีมงานจะตรวจสอบข้อมูลและรูปภาพก่อนนำขึ้นแสดงบนเว็บ': 'Our team will review the details and photos before publishing.',
    'กรุณาเลือกประเภทอสังหาฯ': 'Select a property type',
    'ทุกประเภทอสังหาฯ': 'All property types',
    'ต้องการ': 'Listing type',
    'เลือกประเภทอสังหาฯ': 'Choose a property type',
    'ลิงก์ Google Maps (ไม่บังคับ)': 'Google Maps link (optional)',
    'เพิ่มรูปภาพอสังหาริมทรัพย์ *': 'Add property photos *',
    'เลือกได้สูงสุด': 'Up to',
    'รูป': 'images',
    'ชื่อที่ให้ผู้สนใจติดต่อ': 'Name shown to prospective buyers',
    'กรุณากรอกข้อมูลให้ครบถ้วน': 'Please complete all required fields.',
    'กรอกข้อมูลทรัพย์สินให้ครบถ้วน เพื่อให้ผู้สนใจเห็นรายละเอียดและติดต่อคุณได้ง่ายขึ้น': 'Complete the property details so interested buyers can review the listing and contact you.',
    'ข้อมูลทรัพย์': 'Property details',
    'รูปภาพและติดต่อ': 'Photos & contact',
    'เลือกได้สูงสุด 10 รูป · jpg, png หรือ webp · ไม่เกิน 5 MB ต่อรูป': 'Up to 10 JPG, PNG, or WebP photos; maximum 5 MB each.',
    'ข้าพเจ้ายืนยันว่าเป็นเจ้าของทรัพย์หรือได้รับอนุญาตให้ลงประกาศ และยอมรับข้อตกลงการใช้งานกับนโยบายความเป็นส่วนตัว (ชื่อและเบอร์ใช้เพื่อติดต่อเรื่องประกาศนี้เท่านั้น)': 'I confirm that I own this property or am authorized to list it, and agree to the terms of use and privacy policy. My name and phone number will only be used about this listing.',
    'ยังไม่มีบัญชี?': 'No account yet?',
    'สมัครสมาชิกที่นี่': 'Create an account',
    'มีบัญชีอยู่แล้ว? เข้าสู่ระบบ': 'Already have an account? Log in',
    'เข้าสู่ระบบสมาชิก': 'Member login',
    'สมัครสมาชิกเพื่อลงประกาศ': 'Create an account to list a property',
    'ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร': 'Username must be at least 3 characters.',
    'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร': 'Password must be at least 8 characters.',
    'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง': 'Incorrect username or password.',
    'บัญชีนี้ถูกใช้งานไปแล้ว': 'This account is already in use.',
    'สมัครสมาชิกสำเร็จ! สามารถ': 'Registration successful. You can now',
    'เจ้าของเว็บไซต์': 'Website owner',
    'พื้นที่ส่วนตัวจัดการประกาศ': 'My property dashboard',
    'พื้นที่ส่วนตัว: ประกาศของฉัน': 'My listings',
    'จัดการและตรวจสอบสถานะประกาศอสังหาริมทรัพย์ที่คุณลงไว้': 'Manage your property listings and review their status.',
    'คุณยังไม่มีประกาศอสังหาริมทรัพย์ในระบบ': 'You do not have any property listings yet.',
    'ลงประกาศแรกของคุณ': 'Create your first listing',
    'ผู้ใช้งาน:': 'User:',
    'สถานะ:': 'Status:',
    'อนุมัติแสดงผลแล้ว': 'Published',
    'รอตรวจสอบ': 'Pending review',
    'ไม่อนุมัติ': 'Rejected',
    'กำลังประกาศ': 'Available',
    'ขายแล้ว': 'Sold',
    'ปล่อยเช่าแล้ว': 'Rented',
    'โครงการใหม่': 'New projects',
    'พร้อมอยู่': 'Ready to move in',
    'คอนโดมิเนียม': 'Condominium',
    'บาท/เดือน': 'THB / month',
    'วงเงินกู้': 'Loan amount',
    'สอบถามแบบบ้านทั้งหมด': 'Ask about all home designs',
    'สอบถามข้อมูล Essentia Rama 2': 'Enquire about Essentia Rama 2',
    'โครงการ Essentia Rama 2': 'Essentia Rama 2 project',
    'ถนนพระราม 2': 'Rama II Road',
    'ข้อมูลและนโยบาย': 'Information and policies',
    'คู่มือการใช้งาน': 'User guide',
    'นโยบายความเป็นส่วนตัว': 'Privacy policy',
    'เงื่อนไขการใช้งาน': 'Terms of use',
    'ติดต่อทีมงาน': 'Contact our team',
    'ข้อมูลโครงการและการคำนวณเป็นตัวอย่าง โปรดตรวจสอบรายละเอียดกับผู้พัฒนาโครงการและสถาบันการเงิน': 'Project and mortgage information is illustrative. Verify the details with the developer and financial institution.',
    'ภาพโครงการ': 'Project gallery',
    'ข้อมูลโครงการ': 'Project overview',
    'ประเภทโครงการ': 'Project type',
    'พัฒนาโดย': 'Developed by',
    'สร้างเสร็จ': 'Completed',
    'จำนวนยูนิต': 'Number of units',
    'สิ่งอำนวยความสะดวก': 'Facilities',
    'สถานที่ใกล้เคียง': 'Nearby places',
    'การเดินทาง': 'Transport',
    'โรงเรียน': 'Schools',
    'ห้างสรรพสินค้า': 'Shopping malls',
    'สถานที่พักผ่อน': 'Leisure',
    'ลองคำนวณยอดผ่อนบ้าน': 'Estimate your monthly mortgage',
    'ประมาณการยอดผ่อนต่อเดือน': 'Estimated monthly payment',
    'ราคาอสังหาริมทรัพย์': 'Property price',
    'วงเงินสินเชื่อ': 'Loan amount',
    'ระยะเวลากู้': 'Loan term',
    'อัตราดอกเบี้ยต่อปี': 'Annual interest rate',
    'เดือน': 'month',
    'ปี': 'years',
    'หมวดหมู่': 'Categories',
    'ข้อมูลบริษัท': 'Company information',
    'ติดตามเรา': 'Follow us',
    'สงวนลิขสิทธิ์': 'All rights reserved.',
    'ข้อมูลอ้างอิงจากประกาศจริงบนเว็บไซต์': 'Information is based on listings published on this website.',
    'หน้าเว็บ': 'Website',
    'ค้นหาและตัดสินใจเรื่องอสังหาริมทรัพย์ได้ง่ายยิ่งขึ้นด้วยข้อมูลที่ครบถ้วนและออกแบบแบบมืออาชีพ': 'Find and compare properties with clear, complete information.',
    'คำนวณยอดผ่อนจริงขึ้นอยู่กับอัตราดอกเบี้ย': 'Actual payments depend on the interest rate.',
    'แดชบอร์ด': 'Dashboard',
    '← จัดการประกาศ': '← Manage listings',
    'จัดการประกาศ': 'Manage listings',
    '+ เพิ่มประกาศใหม่': '+ Add listing',
    '🏷️ หมวดแนะนำหน้าแรก': '🏷️ Homepage categories',
    '📊 วิเคราะห์สถิติ': '📊 Analytics',
    '📊 ระบบวิเคราะห์ข้อมูลเชิงลึก (Comprehensive Analytics)': '📊 Website analytics',
    '📊 วิเคราะห์ข้อมูลและสถิติเชิงลึก': '📊 Website analytics',
    'วิเคราะห์ข้อมูลและสถิติเชิงลึก | หลังบ้าน': 'Analytics | Admin',
    'ดูหน้าเว็บไซต์': 'View website',
    'วันนี้': 'Today',
    '7 วันย้อนหลัง': 'Last 7 days',
    '30 วันย้อนหลัง': 'Last 30 days',
    '🏠 สรุปภาพรวมทรัพย์สินในระบบ': '🏠 Listing overview',
    'ประกาศทั้งหมดในระบบ': 'Total listings',
    'ประกาศที่แสดงผลอยู่ (Active)': 'Published listings',
    'ประเภทขาย (Sale)': 'For sale',
    'ประเภทให้เช่า (Rent)': 'For rent',
    '📈 สถิติการเข้าชมเว็บไซต์ (ในช่วงเวลาที่เลือก)': '📈 Website traffic (selected period)',
    'ยอดเปิดหน้าเว็บทั้งหมด (PV)': 'Page views (PV)',
    'ผู้เข้าชมไม่ซ้ำกัน (UV)': 'Unique visitors (UV)',
    'ยอดคลิกดูรายละเอียดประกาศ': 'Property detail views',
    '📈 กราฟแนวโน้มผู้เข้าชมไม่ซ้ำรายวัน': '📈 Daily unique visitor trend',
    '🌐 แหล่งที่มาของผู้เข้าชม (Traffic Sources)': '🌐 Traffic sources',
    'แหล่งที่มา / เว็บไซต์อ้างอิง': 'Referrer',
    'จำนวนครั้ง': 'Visits',
    'ยังไม่มีข้อมูลแหล่งที่มาในช่วงเวลานี้': 'No referral data for this period.',
    '📱 ประเภทอุปกรณ์ที่ผู้ใช้เข้าชม': '📱 Visitor devices',
    'อุปกรณ์': 'Device',
    'จำนวนครั้ง': 'Visits',
    'สัดส่วน': 'Share',
    '📱 มือถือ (Mobile)': '📱 Mobile',
    '💻 คอมพิวเตอร์ (Desktop)': '💻 Desktop',
    '🔥 ประกาศที่ได้รับความสนใจสูงสุด (Top 10 Listings)': '🔥 Most viewed listings',
    'หัวข้อประกาศ': 'Listing title',
    'ประเภท': 'Type',
    'สถานะ': 'Status',
    'ยอดดู (Views)': 'Views',
    'ผู้เข้าชม (UV)': 'Unique visitors',
    'ยังไม่มีข้อมูลการคลิกดูประกาศในช่วงเวลานี้': 'No property views for this period.',
    '👥 ประวัติการเข้าชมแบบเรียลไทม์ (Real-time Visitor Log)': '👥 Recent visitor activity',
    'บันทึกพฤติกรรมการเข้าชมล่าสุด 25 รายการ': 'Latest 25 recorded visits',
    'ยังไม่มีประวัติการเข้าชมในช่วงเวลานี้': 'No visits recorded for this period.',
    'เวลา': 'Time',
    'รหัสผู้เข้าชม (Visitor Hash)': 'Visitor ID',
    'กิจกรรม / หน้าที่เข้าชม': 'Activity / page',
    'ดูประกาศ:': 'Viewed listing:',
    'เข้าชมหน้าแรก / หน้าทั่วไป': 'Visited a page',
    'สถิติผู้เข้าชม': 'Visitor analytics',
    'แสดงอยู่': 'Published',
    'ทั้งหมด': 'All',
    'อนุมัติ': 'Approve',
    'แก้ไข': 'Edit',
    'ลบ': 'Delete',
    'เพิ่มประกาศใหม่': 'Add a listing',
    'รูปภาพ': 'Photo',
    'ผู้ติดต่อ': 'Contact',
    'สถานะอนุมัติ': 'Approval',
    'สถานะทรัพย์': 'Listing status',
    'การจัดการ': 'Actions',
    'ไม่มีประกาศในหมวดหมู่นี้': 'No listings in this category.',
    'เข้าสู่ระบบผู้ดูแล': 'Owner login',
    'สำหรับดูสถิติผู้เข้าชมและจัดการประกาศ': 'View visitor analytics and manage listings.',
    'กลับไปหน้าจัดการประกาศ': 'Back to listing management',
    'กลับ': 'Back',
    'ภาษาไทย': 'Thai',
    'ภาษาอังกฤษ': 'English',
    'ตั้งค่าระบบ / แอดมิน': 'Owner tools'
  };

  Object.assign(translations, {
    'ซื้อ ▾': 'Buy ▾',
    'เช่า ▾': 'Rent ▾',
    'ค้นหายอดนิยม': 'Popular searches',
    'บ้านที่ใช่ เริ่มต้นจากทำเลที่ชอบ': 'Find the right home in the right location',
    'ค้นหาบ้านในแบบที่เป็นคุณ': 'Find a home that fits your lifestyle',
    'ค้นหาบ้าน คอนโด และอสังหาริมทรัพย์ทั่วไทย พร้อมติดต่อเจ้าของได้โดยตรง': 'Search homes, condos, and properties across Thailand and contact owners directly.',
    'ทุกประเภทอสังหาฯ': 'All property types',
    'ดูประกาศทั้งหมด ›': 'View all listings ›',
    'มีทรัพย์ต้องการลงประกาศ?': 'Have a property to list?',
    'ลงประกาศฟรี ทีมงานตรวจสอบก่อนแสดงบนเว็บทุกครั้ง': 'Listing is free. Every submission is reviewed before it is published.',
    'อีเมลติดต่อ:': 'Contact email:',
    'หมวดอสังหาริมทรัพย์แนะนำ': 'Featured property categories',
    'ค้นหาตามประเภทอสังหาริมทรัพย์': 'Browse by property type',
    'วีดีโอแนะนำโครงการ': 'Project video',
    'วิดีโอแนะนำโครงการ': 'Project video',
    'พัฒนาโครงการโดย': 'Developed by',
    'ประมาณ 250 ยูนิต': 'Approximately 250 units',
    'คาดว่าจะแล้วเสร็จ': 'Expected completion',
    'ระยะทาง': 'Distance',
    'ล้านบาท': 'million baht',
    'กม.': 'km',
    'หมวดหมู่อสังหาริมทรัพย์': 'Property categories',
    'ขาย/ให้เช่า คอนโดมือสอง': 'Resale condos for sale or rent',
    'ขาย/ให้เช่า บ้านเดี่ยว': 'Houses for sale or rent',
    'ขาย/ให้เช่า ทาวน์เฮ้าส์ / ทาวน์โฮม': 'Townhouses for sale or rent',
    'ขาย/ให้เช่า ที่ดินทำเลทอง': 'Land for sale or rent',
    'บริการ & ติดต่อเรา': 'Services & contact',
    '➕ ลงประกาศขาย/เช่า ฟรี': '➕ List a property for free',
    '🔍 ค้นหาประกาศทั้งหมด': '🔍 Search all listings',
    'ข้อมูลมีไว้เพื่อประกอบการพิจารณาเท่านั้น': 'Information is provided for reference only.',
    'รูปแบบการขาย': 'Ownership type',
    'ขายขาด': 'Freehold',
    'Essentia Rama 2, สมุทรสาคร': 'Essentia Rama 2, Samut Sakhon',
    'ถนนพระราม 2 ตำบลนาดี อำเภอเมืองสมุทรสาคร จังหวัดสมุทรสาคร': 'Rama II Road, Na Di, Mueang Samut Sakhon, Samut Sakhon',
    'ดูภาพบ้านตัวอย่าง': 'View the model home',
    'ดูภาพห้องนั่งเล่น': 'View the living room',
    'ดูภาพพื้นที่รับประทานอาหาร': 'View the dining area',
    'ดูภาพบ้านและสวน': 'View the house and garden',
    'บ้านเดี่ยวสไตล์โมเดิร์นในโครงการ Essentia Rama 2': 'Modern detached house at Essentia Rama 2',
    'ห้องนั่งเล่นภายในบ้านตัวอย่าง': 'Living room in the model home',
    'พื้นที่รับประทานอาหารในบ้านตัวอย่าง': 'Dining area in the model home',
    'บ้านและพื้นที่สีเขียวในโครงการ': 'House and green spaces in the project',
    'HAVEN ทาวน์ (Type A)': 'HAVEN Townhouse (Type A)',
    'SOLACE โซเลส': 'SOLACE',
    'SERENE ซีรีน': 'SERENE',
    'มหาชัย': 'Mahachai',
    'ถ. เอกชัย': 'Ekkachai Road',
    'ถ. พระราม 2': 'Rama II Road',
    'ถนนเอกชัย': 'Ekkachai Road',
    'สถานีรถไฟมหาชัย': 'Mahachai Railway Station',
    'ระยะทางเป็นข้อมูลตัวอย่าง ควรตรวจสอบเส้นทางและเวลาเดินทางจริงก่อนตัดสินใจ': 'Distances are illustrative. Check actual routes and travel times before making a decision.',
    'ปรับตัวเลขเพื่อดูประมาณการเบื้องต้น ผลคำนวณไม่ใช่ข้อเสนอสินเชื่อจากสถาบันการเงิน': 'Adjust the values for an estimate. This calculation is not a loan offer from a financial institution.',
    'เงินดาวน์': 'Down payment',
    'คำนวณแบบลดต้นลดดอกด้วยอัตราดอกเบี้ยคงที่ตามค่าที่กรอก ยังไม่รวมค่าธรรมเนียม ประกัน และเงื่อนไขเฉพาะของแต่ละธนาคาร': 'Calculated using a fixed interest rate and reducing-balance method. Fees, insurance, and bank-specific terms are not included.',
    'ยอดผ่อนจริงขึ้นอยู่กับอัตราดอกเบี้ย โปรโมชัน วงเงินอนุมัติ และคุณสมบัติผู้กู้ โปรดสอบถามธนาคารก่อนตัดสินใจ': 'Actual payments depend on interest, promotions, approved loan, and borrower eligibility. Contact a bank before deciding.',
    'สนใจโครงการนี้?': 'Interested in this project?',
    'ติดต่อทีมงานเพื่อขอรายละเอียดแบบบ้าน ราคา และเงื่อนไขโครงการ': 'Contact our team for home designs, pricing, and project terms.',
    'ส่งอีเมลสอบถาม': 'Send an enquiry',
    'ค้นหาบ้านและอสังหาริมทรัพย์ พร้อมข้อมูลโครงการและทำเลเพื่อช่วยให้คุณตัดสินใจได้ง่ายขึ้น': 'Search homes and properties with project and location information to help you decide.',
    'พร้อมขาย': 'Available for sale',
    'ติดต่อ': 'Contact',
    'โทรสอบถามทันที': 'Call now',
    'ผู้ลงประกาศยังไม่ได้เพิ่มรายละเอียดเพิ่มเติม': 'The advertiser has not added a description yet.',
    'รูปแบบ': 'Type',
    'รหัสประกาศ': 'Listing ID',
    'ระบบรักษาความปลอดภัย': 'Security system',
    'รหัสประกาศนี้ไม่มีแล้ว': 'This listing is no longer available.',
    'เลือกแบบบ้านที่ใช่': 'Choose a home design',
    'สอบถามแบบบ้านทั้งหมด →': 'Ask about all home designs →',
    'บ้านเดี่ยว 2 ชั้น': 'Two-storey detached house',
    'ตร.ม.': 'sq m',
    'ที่ดินเริ่มต้น 50 ตร.ว.': 'Land from 50 sq wah',
    'รับรายละเอียดแบบบ้าน': 'Request home design details',
    'รายละเอียดที่ออกแบบเพื่อทุกวัน': 'Designed for everyday living',
    'ส่วนกลางและระบบรักษาความปลอดภัย': 'Shared facilities and security',
    'CCTV รอบโครงการ': 'CCTV throughout the project',
    'พื้นที่พักผ่อนส่วนกลาง': 'Shared lounge area',
    'รองรับทุกครอบครัว': 'Designed for families',
    'สวนส่วนกลาง': 'Shared garden',
    'พื้นที่สีเขียวในโครงการ': 'Green spaces within the project',
    'ระบบเข้า-ออก': 'Access control',
    'ควบคุมการเข้าออก': 'Controlled entry and exit',
    'เจ้าหน้าที่รักษาความปลอดภัย': 'Security staff',
    'ดูแลตลอด 24 ชั่วโมง': 'On-site 24-hour security',
    'เดินทางสะดวก ใกล้ทุกจุดหมาย': 'Convenient access to nearby destinations',
    'เส้นทางหลักเชื่อมต่อกรุงเทพฯ และภาคใต้': 'A main route connecting Bangkok and southern Thailand',
    'ใกล้โครงการ': 'Near the project',
    'เส้นทางเชื่อมต่อย่านมหาชัย': 'Route connecting to the Mahachai area',
    'เดินทางเข้าสู่ตัวเมืองสมุทรสาคร': 'Access to Samut Sakhon city',
    'ยังไม่มีประกาศในหมวดนี้ที่อนุมัติแล้ว': 'No approved listings in this category yet.',
    'ผลการค้นหา': 'Search results',
    'ไม่พบประกาศที่ตรงกับเงื่อนไข': 'No listings match your search.',
    'พบ': 'Found',
    'ลงประกาศเป็นคนแรก': 'Be the first to list a property',
    'ราคา': 'Price',
    'เงินกู้': 'Loan',
    'ข้อมูลส่วนตัวของฉัน': 'My account',
    'อัปเดตข้อมูลโครงการล่าสุด': 'Project information updated',
    'รูปแบบบ้าน': 'Home types',
    'บ้านเดี่ยว': 'Detached house',
    'บ้านตัวอย่าง': 'Show home',
    'สิ่งอำนวยความสะดวกโดยรอบ': 'Nearby facilities',
    'คลับเฮาส์': 'Clubhouse',
    'ที่จอดรถ': 'Parking',
    'กล้องวงจรปิด': 'CCTV',
    'ส่วนกลาง': 'Shared facilities',
    'สร้างเสร็จปี': 'Completion year',
    'จำนวนยูนิตทั้งหมด': 'Total units',
    'พื้นที่ใช้สอย': 'Usable area',
    'ห้องนอน': 'Bedrooms',
    'ห้องน้ำ': 'Bathrooms',
    'ตั้งแต่': 'From',
    'ราคาเริ่มต้น': 'Starting price',
    'พร้อมอยู่': 'Ready to move in',
    'สอบถามเลย': 'Enquire now',
    'บันทึก': 'Save',
    'แชร์': 'Share',
    'แชร์ประกาศ': 'Share listing',
    'คัดลอกแล้ว': 'Copied',
    'บันทึกแล้ว': 'Saved',
    'แจ้งทีมงานเพื่อลงประกาศ': 'Contact us to list your property',
    'คุณยังไม่มีประกาศในระบบ': 'You do not have any listings yet.',
    'สวัสดี': 'Hello',
    'แดชบอร์ดเจ้าของเว็บไซต์': 'Website owner dashboard',
    'PRIVATE OWNER AREA': 'PRIVATE OWNER AREA',
    'ประกาศพร้อมขาย/เช่าที่เผยแพร่แล้ว': 'Published and available listings',
    'ประกาศทั้งหมด': 'Total listings',
    'เผยแพร่แล้ว': 'Published',
    'ผู้เข้าชมไม่ซ้ำ 30 วัน': 'Unique visitors (30 days)',
    'วิเคราะห์สถิติผู้เข้าชม': 'Visitor analytics',
    'จัดการประกาศ': 'Manage listings',
    'จัดการหมวดแนะนำ': 'Manage featured categories',
    'ประกาศของฉัน': 'My listings',
    'ติดตามสถานะประกาศของคุณได้ที่นี่': 'Track your listing status here.',
    'ลงประกาศใหม่': 'Create a listing',
    'รอตรวจสอบ': 'Pending review',
    'หมวดแนะนำหน้าแรก': 'Homepage categories',
    'ไม่มีข้อมูลหน้าที่เข้าชมในช่วงเวลานี้': 'No page activity for this period.',
    '🏘️ ประกาศแยกตามประเภทอสังหาริมทรัพย์': '🏘️ Published listings by property type',
    'จำนวนประกาศที่เผยแพร่': 'Published listings',
    'ประกาศพร้อมขาย/เช่าที่เผยแพร่แล้ว': 'Published listings available for sale or rent',
    'แสดงจำนวนผู้เข้าชมที่ไม่ซ้ำในแต่ละวัน รวมการเปิดหน้าเว็บและดูรายละเอียดประกาศ': 'Daily unique visitors, including page views and property detail views.',
    'แสดงรหัสผู้เข้าชมแบบสุ่ม อุปกรณ์ เบราว์เซอร์ และหน้าที่เข้าชม โดยไม่แสดงหรือเก็บ IP address': 'Shows random visitor IDs, device, browser, and page visited. IP addresses are not stored or displayed.',
    'ยังไม่มีประกาศในระบบ': 'There are no listings yet.',
    'อีเมลหรือชื่อผู้ใช้นี้ถูกใช้งานไปแล้ว': 'That email address or username is already in use.',
    'รูปแบบอีเมลไม่ถูกต้อง': 'Please enter a valid email address.',
    'กรุณากรอกข้อมูลให้ครบถ้วน (อีเมล, ชื่อผู้ใช้ และรหัสผ่าน)': 'Please complete the email, username, and password fields.',
    'เกิดข้อผิดพลาดในการสมัครสมาชิก': 'Could not create the account. Please try again.',
    'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน': 'Enter your username and password.',
    'เข้าสู่ระบบสมาชิก': 'Log in',
    'ค้นหาทำเล เช่น สุขุมวิท, ลาดพร้าว': 'Search locations, e.g. Sukhumvit or Lat Phrao',
    'ค้นหาทำเล เช่น สุขุมวิท, ภูเก็ต, ลาดพร้าว': 'Search locations, e.g. Sukhumvit, Phuket, or Lat Phrao',
    'เช่น คอนโด 1 ห้องนอน ใกล้ BTS': 'e.g. One-bedroom condo near the BTS',
    'เช่น 3500000': 'e.g. 3500000',
    'เช่น สุขุมวิท 39, กรุงเทพฯ': 'e.g. Sukhumvit 39, Bangkok',
    'วางลิงก์จาก Google Maps ที่ปักหมุดแล้ว': 'Paste a pinned Google Maps link',
    'เช่น ขนาดพื้นที่ จำนวนห้อง สิ่งอำนวยความสะดวก และจุดเด่นของทำเล': 'Describe the floor area, rooms, facilities, and location highlights.',
    'กรุณากรอกชื่อและข้อความให้ครบถ้วน': 'Please enter your name and message.',
    'สมุดเยี่ยมชมเว็บไซต์ (Guestbook)': 'Website guestbook',
    'ชื่อของคุณ *': 'Your name *',
    'อีเมล (ไม่แสดงสาธารณะ)': 'Email (not shown publicly)',
    'ข้อความ / ทักทาย *': 'Message *',
    'รายชื่อผู้ที่เคยแวะมาเยี่ยมชมล่าสุด': 'Recent guestbook messages',
    'ยังไม่มีผู้ฝากข้อความ เป็นคนแรกที่ทักทายได้เลย!': 'No messages yet. Be the first to say hello!',
    'ติดตามและวิเคราะห์ข้อมูลผู้เข้าชม': 'Track and analyze site visitors',
    'คำขอหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่': 'The request expired or is invalid. Refresh the page and try again.',
    'เพิ่มรูปภาพอสังหาริมทรัพย์ *': 'Add property photos *',
    'ข้อมูลสรุป': 'Overview',
    'แบบบ้าน': 'Home designs',
    'ลักษณะการขาย': 'Sale type',
    'คำนวณสินเชื่อ': 'Mortgage calculator',
    'สถานที่ใกล้เคียง': 'Nearby places',
    'พบประกาศที่ตรงกับเงื่อนไข': 'Matching listings found',
    'ค้นหาประกาศทั้งหมด': 'Search all listings',
    'ประเภทอสังหาฯ *': 'Property type *',
    'ต้องการ *': 'Listing type *',
    'ราคา (บาท) *': 'Price (THB) *',
    'ทำเล / ที่อยู่ *': 'Location / address *',
    'ชื่อผู้ติดต่อ *': 'Contact name *',
    'เบอร์โทรศัพท์ *': 'Phone number *',
    'หมวดหมู่ยอดนิยม': 'Popular categories',
    'ติดตามเรา': 'Follow us',
    'บ้านและโครงการ': 'Homes & projects',
    'คอนโดเเนะนำ': 'Featured condos',
    'ศูนย์รวมประกาศซื้อ ขาย ให้เช่า อสังหาริมทรัพย์ คอนโด บ้านเดี่ยว ทาวน์เฮ้าส์ ที่ดิน ทำเลดีทั่วประเทศ ค้นหาง่าย ติดต่อเจ้าของทรัพย์ได้โดยตรง': 'Browse properties for sale and rent across Thailand, including condos, houses, townhouses, and land. Search easily and contact owners directly.',
    'ติดตั้งระบบ': 'System setup',
    'สร้างบัญชีเจ้าของเว็บไซต์': 'Create owner account',
    'กรุณากรอกชื่อผู้ใช้ อีเมล และรหัสผ่านอย่างน้อย 8 ตัวอักษรให้ถูกต้อง': 'Enter a valid username, email, and password of at least 8 characters.',
    'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่แล้ว': 'That username or email is already in use.',
    'สร้างบัญชีเจ้าของเว็บไซต์เรียบร้อยแล้ว': 'Owner account created successfully.',
    'ติดตั้งเรียบร้อยแล้ว กรุณา': 'Setup is complete. Please',
    'ลบไฟล์ install.php ออกจากเซิร์ฟเวอร์': 'remove install.php from the server'
  });
  Object.assign(translations, {
    'Standard Property | ประกาศขาย-เช่าอสังหาริมทรัพย์': 'Standard Property | Properties for sale and rent',
    'ค้นหาอสังหาริมทรัพย์ | Standard Property': 'Search properties | Standard Property',
    'ลงประกาศฟรี | Standard Property': 'List a property for free | Standard Property',
    'เข้าสู่ระบบ | Standard Property': 'Log in | Standard Property',
    'สมัครสมาชิก | Standard Property': 'Create account | Standard Property',
    'Essentia Rama 2 | โครงการบ้านเดี่ยว สมุทรสาคร | Standard Property': 'Essentia Rama 2 | Detached houses in Samut Sakhon | Standard Property',
    'สมุดเยี่ยมชมเว็บไซต์ | Standard Property': 'Website guestbook | Standard Property',
    'ไม่พบหน้าที่ต้องการ | Standard Property': 'Page not found | Standard Property',
    'จัดการประกาศ | ระบบหลังบ้าน': 'Manage listings | Admin',
    'จัดการหมวดแนะนำหน้าแรก | หลังบ้าน': 'Featured categories | Admin',
    'แดชบอร์ดเจ้าของเว็บไซต์ | Standard Property': 'Website owner dashboard | Standard Property',
    'ประกาศของฉัน | Standard Property': 'My listings | Standard Property',
    'เปลี่ยนรหัสผ่านบัญชีเจ้าของเว็บ': 'Change owner account password',
    'รหัสผ่านปัจจุบัน': 'Current password',
    'รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)': 'New password (at least 8 characters)',
    'ยืนยันรหัสผ่านใหม่': 'Confirm new password',
    'บันทึกรหัสผ่านใหม่': 'Save new password',
    'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว': 'Password changed successfully.',
    'รหัสผ่านปัจจุบันไม่ถูกต้อง': 'The current password is incorrect.',
    'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร': 'The new password must be at least 8 characters.',
    'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน': 'The new passwords do not match.'
  });
  function getCookie(name) {
    const prefix = `${name}=`;
    const value = document.cookie.split('; ').find((cookie) => cookie.startsWith(prefix));
    return value ? decodeURIComponent(value.slice(prefix.length)) : '';
  }

  const attributeNames = ['placeholder', 'title', 'aria-label', 'alt'];
  const originalText = new WeakMap();
  const originalAttributes = new WeakMap();

  const urlParams = new URLSearchParams(window.location.search);
  const urlLang = (urlParams.get('lang') || '').toLowerCase();
  const htmlLang = (document.documentElement.lang || '').toLowerCase();
  const cookieLang = (getCookie('sp_lang') || '').toLowerCase();

  let language = ['en', 'th'].includes(urlLang)
    ? urlLang
    : (['en', 'th'].includes(htmlLang)
      ? htmlLang
      : (['en', 'th'].includes(cookieLang)
        ? cookieLang
        : (localStorage.getItem('site-language') || 'th')));

  if (language !== 'en' && language !== 'th') language = 'th';
  try {
    localStorage.setItem('site-language', language);
  } catch {}
  document.cookie = `sp_lang=${language}; path=/; max-age=31536000; SameSite=Lax`;

  function switchLanguage(target) {
    target = target === 'en' ? 'en' : 'th';
    try {
      localStorage.setItem('site-language', target);
    } catch {}
    document.cookie = `sp_lang=${target}; path=/; max-age=31536000; SameSite=Lax`;
    const url = new URL(window.location.href);
    url.searchParams.set('lang', target);
    window.location.href = url.toString();
  }

  // Intercept click on any .lang-toggle link to ensure immediate storage update
  document.addEventListener('click', (e) => {
    const toggle = e.target.closest('.lang-toggle');
    if (!toggle) return;
    const target = toggle.getAttribute('data-lang-target') || (language === 'en' ? 'th' : 'en');
    try {
      localStorage.setItem('site-language', target);
    } catch {}
    document.cookie = `sp_lang=${target}; path=/; max-age=31536000; SameSite=Lax`;
  });
  function translateText(text) {
    const trimmed = text.trim();
    if (!trimmed) return text;
    if (translations[trimmed]) return text.replace(trimmed, translations[trimmed]);

    let match = trimmed.match(/^พบ\s+([\d,]+)\s+ประกาศ(?:ที่ตรงกับเงื่อนไข)?$/);
    if (match) return text.replace(trimmed, `${match[1]} listings found`);
    match = trimmed.match(/^รวมประกาศ\s+(.+)$/);
    if (match) return text.replace(trimmed, `Browse ${translations[match[1]] || match[1]} listings`);
    match = trimmed.match(/^©\s*(\d+)\s+(.+?)\s+สงวนลิขสิทธิ์$/);
    if (match) return text.replace(trimmed, `© ${match[1]} ${match[2]}. All rights reserved.`);
    match = trimmed.match(/^(.+?)\s+บาท\/เดือน$/);
    if (match) return text.replace(trimmed, `${match[1]} THB / month`);
    match = trimmed.match(/^(.+?)\s+บาท$/);
    if (match) return text.replace(trimmed, `${match[1]} THB`);
    match = trimmed.match(/^เริ่มต้น\s+([\d,.]+)\s+ล้านบาท$/);
    if (match) return text.replace(trimmed, `Starting at THB ${match[1]} million`);
    match = trimmed.match(/^([\d,.]+)\s+ล้านบาท$/);
    if (match) return text.replace(trimmed, `THB ${match[1]} million`);
    match = trimmed.match(/^ประมาณ\s+([\d,.]+)\s+ยูนิต$/);
    if (match) return text.replace(trimmed, `Approximately ${match[1]} units`);
    match = trimmed.match(/^ปี\s+(\d{4})$/);
    if (match) return text.replace(trimmed, `Year ${match[1]}`);
    match = trimmed.match(/^ประมาณ\s+([\d,.]+)\s+กม\.$/);
    if (match) return text.replace(trimmed, `Approximately ${match[1]} km`);
    match = trimmed.match(/^(\d+)\s*ตร\.ม\.$/);
    if (match) return text.replace(trimmed, `${match[1]} sq m`);
    match = trimmed.match(/^📧\s*อีเมลติดต่อ:\s*(.+)$/);
    if (match) return text.replace(trimmed, `📧 Contact email: ${match[1]}`);
    match = trimmed.match(/^ขาย\s*-\s*ให้เช่า\s+(.+)$/);
    if (match) return text.replace(trimmed, `For sale and rent: ${translations[match[1]] || match[1]}`);
    return text;
  }

  function translateTitle(title) {
    if (translations[title]) return translations[title];

    let match = title.match(/^รวมประกาศ\s+(.+?)\s+\|\s+(.+)$/);
    if (match) return `Browse ${translations[match[1]] || match[1]} | ${match[2]}`;

    match = title.match(/^(.+?)\s+\|\s+ระบบหลังบ้าน$/);
    if (match) return `${translations[match[1]] || match[1]} | Admin`;

    return title;
  }

  function applyLanguage() {
    document.documentElement.lang = language === 'en' ? 'en' : 'th';
    const originalTitle = document.title;
    if (!document.documentElement.dataset.originalTitle) {
      document.documentElement.dataset.originalTitle = originalTitle;
    }
    document.title = language === 'en'
      ? translateTitle(document.documentElement.dataset.originalTitle)
      : document.documentElement.dataset.originalTitle;
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    let node;
    while ((node = walker.nextNode())) {
      if (!originalText.has(node)) originalText.set(node, node.nodeValue);
      const parent = node.parentElement;
      if (!parent || parent.closest('script, style, textarea, code, [data-no-translate]')) continue;
      node.nodeValue = language === 'en'
        ? translateText(originalText.get(node))
        : originalText.get(node);
    }

    document.querySelectorAll('*').forEach((element) => {
      if (!originalAttributes.has(element)) {
        originalAttributes.set(element, {});
        attributeNames.forEach((name) => {
          if (element.hasAttribute(name)) originalAttributes.get(element)[name] = element.getAttribute(name);
        });
      }
      attributeNames.forEach((name) => {
        const original = originalAttributes.get(element)[name];
        if (original) element.setAttribute(name, language === 'en' ? (translations[original] || original) : original);
      });
    });
    document.querySelectorAll('[data-site-language]').forEach((button) => {
      const label = language === 'en' ? 'ไทย' : 'EN';
      const ariaLabel = language === 'en' ? 'Switch to Thai / เปลี่ยนเป็นภาษาไทย' : 'Switch to English / เปลี่ยนเป็นภาษาอังกฤษ';
      if (button.textContent !== label) button.textContent = label;
      if (button.getAttribute('aria-label') !== ariaLabel) button.setAttribute('aria-label', ariaLabel);
      if (button.getAttribute('title') !== ariaLabel) button.setAttribute('title', ariaLabel);
    });
    const backButton = document.querySelector('[data-back-button]');
    if (backButton) {
      const backLabel = language === 'en' ? '← Back' : '← ย้อนกลับ';
      if (backButton.textContent !== backLabel) backButton.textContent = backLabel;
    }
  }

  function fallbackBackUrl() {
    const path = location.pathname;
    const root = path.replace(/(?:admin|api)\/[^/]*$/, '').replace(/[^/]*$/, '');
    const customBack = document.body.dataset.backUrl;
    if (customBack) return customBack;
    if (/\/admin\/(login|logout)\.php$/.test(path)) return root + 'index.php';
    if (/\/admin\//.test(path)) return root + 'admin/index.php';
    if (/\/submit\.php$/.test(path)) return root + 'dashboard.php';
    return root + 'index.php';
  }

  function addCookieConsent() {
    const controlsScript = Array.from(document.scripts).find((script) => script.src.includes('/js/site-controls.js'));
    if (!controlsScript) return;
    const consentUrl = new URL('../api/cookie_log.php', controlsScript.src).href;
    const trackingUrl = new URL('../api/track.php', controlsScript.src).href;
    const panel = document.createElement('section');
    panel.className = 'cookie-consent';
    panel.setAttribute('aria-label', 'Cookie preferences');
    panel.hidden = true;

    const heading = document.createElement('h2');
    heading.textContent = 'นโยบายคุกกี้';
    const message = document.createElement('p');
    message.textContent = 'เราใช้คุกกี้ที่จำเป็นเพื่อให้เว็บไซต์และระบบเข้าสู่ระบบทำงาน ส่วนคุกกี้วิเคราะห์ผู้เข้าชมเป็นทางเลือกและจะเริ่มหลังจากคุณยินยอมเท่านั้น';
    const error = document.createElement('p');
    error.className = 'cookie-consent-error';
    error.setAttribute('role', 'alert');
    const actions = document.createElement('div');
    actions.className = 'cookie-consent-actions';
    const accept = document.createElement('button');
    accept.type = 'button';
    accept.className = 'cookie-consent-accept';
    accept.textContent = 'ยอมรับคุกกี้วิเคราะห์';
    const essential = document.createElement('button');
    essential.type = 'button';
    essential.className = 'cookie-consent-essential';
    essential.textContent = 'ใช้เฉพาะคุกกี้ที่จำเป็น';
    actions.append(accept, essential);
    panel.append(heading, message, error, actions);
    document.body.append(panel);

    window.spOpenCookieConsent = () => {
      panel.hidden = false;
      accept.focus();
    };

    const flushTracking = async () => {
      if (getCookie('sp_analytics_consent') !== 'accepted') return;
      const events = (window.spPendingEvents || []).splice(0);
      for (const event of events) {
        try {
          await fetch(trackingUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(event),
            keepalive: true
          });
        } catch {
          window.spPendingEvents.unshift(event);
          return;
        }
      }
    };
    window.spFlushTracking = flushTracking;

    const saveChoice = async (choice) => {
      error.textContent = '';
      accept.disabled = true;
      essential.disabled = true;
      try {
        const response = await fetch(consentUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ choice })
        });
        if (!response.ok) throw new Error('Consent could not be saved');
        if (choice === 'denied') window.spPendingEvents = [];
        panel.hidden = true;
        await flushTracking();
      } catch {
        error.textContent = 'บันทึกการตั้งค่าไม่สำเร็จ กรุณาลองอีกครั้ง';
      } finally {
        accept.disabled = false;
        essential.disabled = false;
        applyLanguage();
      }
    };
    accept.addEventListener('click', () => saveChoice('accepted'));
    essential.addEventListener('click', () => saveChoice('denied'));

    if (!['accepted', 'denied'].includes(getCookie('sp_analytics_consent'))) {
      panel.hidden = false;
    } else {
      flushTracking();
    }
    applyLanguage();
  }

  function isHomePage() {
    const p = location.pathname.toLowerCase().replace(/\/+$/, '');
    return p.endsWith('/index.php') || p.endsWith('/property test v.2') || p.endsWith('/property%20test%20v.2') || p === '' || document.body.classList.contains('home-page') || !!document.querySelector('.dd-navbar');
  }

  function addControls() {
    addCookieConsent();

    // Never show floating back/controls toolbar on the home page
    if (isHomePage()) {
      applyLanguage();
      return;
    }

    document.body.classList.add('has-site-controls');
    const controls = document.createElement('nav');
    controls.className = 'site-controls';
    controls.setAttribute('aria-label', 'Page controls');

    const back = document.createElement('a');
    back.className = 'site-control-button';
    back.href = fallbackBackUrl();
    back.dataset.backButton = '';
    back.textContent = language === 'en' ? '← Back' : '← ย้อนกลับ';
    if (document.referrer) {
      try {
        const previous = new URL(document.referrer);
        const previousIsAuthPage = /\/(login|register|logout|submit)\.php$/.test(previous.pathname);
        if (previous.origin === location.origin && previous.href !== location.href && !previousIsAuthPage) {
          back.href = previous.href;
        }
      } catch {
        back.href = fallbackBackUrl();
      }
    }

    controls.append(back);
    document.body.append(controls);
    applyLanguage();

    let isApplying = false;
    new MutationObserver(() => {
      if (isApplying) return;
      isApplying = true;
      try {
        applyLanguage();
      } finally {
        isApplying = false;
      }
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addControls, { once: true });
  } else {
    addControls();
  }
})();

/* ===== Dropdown เมนูซื้อ/เช่า: คลิกเพื่อเปิด-ปิด (ทุกหน้า) ===== */
(function () {
  'use strict';

  function closeAll(except) {
    document.querySelectorAll('.nav-dropdown.open').forEach(function (d) {
      if (d !== except) {
        d.classList.remove('open');
        var t = d.querySelector('.nav-dropdown-toggle');
        if (t) t.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function initNavDropdowns() {
    document.querySelectorAll('.nav-dropdown').forEach(function (dropdown) {
      if (dropdown.dataset.navDropdownReady === '1') return;
      dropdown.dataset.navDropdownReady = '1';

      var toggle = dropdown.querySelector('.nav-dropdown-toggle');
      var panel = dropdown.querySelector('.nav-dropdown-panel');
      if (!toggle || !panel) return;

      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-haspopup', 'true');

      toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var willOpen = !dropdown.classList.contains('open');
        closeAll(dropdown);
        dropdown.classList.toggle('open', willOpen);
        toggle.setAttribute('aria-expanded', String(willOpen));
      });

      var closeTimer = null;
      dropdown.addEventListener('mouseenter', function () {
        if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
      });
      dropdown.addEventListener('mouseleave', function () {
        if (!dropdown.classList.contains('open')) return;
        closeTimer = setTimeout(function () {
          dropdown.classList.remove('open');
          toggle.setAttribute('aria-expanded', 'false');
        }, 300);
      });
    });

    document.addEventListener('click', function (event) {
      if (!event.target.closest('.nav-dropdown')) closeAll(null);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeAll(null);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNavDropdowns, { once: true });
  } else {
    initNavDropdowns();
  }
})();
# Start - نظام إدارة المحتوى

نظام إدارة محتوى متكامل مبني باستخدام PHP و MySQL مع واجهة مستخدم حديثة.

## المميزات

- نظام تسجيل دخول وإدارة المستخدمين
- إدارة المقالات والمدونة
- محرر نصوص متقدم (CKEditor)
- رفع وإدارة الصور
- واجهة مستخدم سهلة الاستخدام
- تصميم متجاوب

## متطلبات النظام

- PHP 7.4 أو أحدث
- MySQL 5.7 أو أحدث
- خادم ويب (Apache/Nginx)
- Composer (لإدارة التبعيات)

## التثبيت

1. قم بنسخ المشروع:
```bash
git clone https://github.com/yourusername/Start.git
cd Start
```

2. قم بإنشاء قاعدة البيانات وتكوين الاتصال:
- قم بإنشاء قاعدة بيانات جديدة
- قم بتعديل ملف `connect.php` بمعلومات الاتصال الخاصة بك

3. قم بإنشاء المجلدات المطلوبة:
```bash
mkdir -p uploads/blog_images
mkdir -p uploads/editor_images
chmod 777 uploads/blog_images
chmod 777 uploads/editor_images
```

4. قم بتثبيت التبعيات:
```bash
composer install
npm install
```

5. قم بتشغيل المشروع على خادم محلي:
```bash
php -S localhost:8000
```

## الهيكل

```
Start/
├── assets/
│   ├── css/
│   ├── js/
│   ├── img/
│   └── editor/
├── cpanel/
│   ├── includes/
│   └── Sections/
├── uploads/
│   ├── blog_images/
│   └── editor_images/
└── includes/
```

## المساهمة

نرحب بمساهماتكم! يرجى اتباع الخطوات التالية:

1. قم بعمل Fork للمشروع
2. قم بإنشاء فرع جديد (`git checkout -b feature/AmazingFeature`)
3. قم بعمل Commit للتغييرات (`git commit -m 'Add some AmazingFeature'`)
4. قم بعمل Push للفرع (`git push origin feature/AmazingFeature`)
5. قم بفتح Pull Request

## الترخيص

هذا المشروع مرخص تحت رخصة MIT - انظر ملف [LICENSE](LICENSE) للتفاصيل. 
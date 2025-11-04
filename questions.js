const formQuestions = [
    // === 1. 📝 פרטים אישיים (מתוך הטבלה הראשית) ===
    { id: "personal_ma", question: "מ.א. (מספר אישי):", type: "text", required: true },
    { id: "personal_rank", question: "דרגה:", type: "text", required: true },
    { id: "personal_lastName", question: "שם משפחה:", type: "text", required: true },
    { id: "personal_firstName", question: "שם פרטי:", type: "text", required: true },
    { id: "personal_birthDate", question: "תאריך לידה (עברי ולועזי):", type: "text", required: true },
    { id: "personal_age", question: "גיל:", type: "number", required: true },
    { id: "personal_status", question: "מצב משפחתי:", type: "text", required: true },
    { id: "personal_childrenCount", question: "מספר ילדים:", type: "number", required: false },
    { id: "personal_spouseName", question: "שם בן/בת הזוג:", type: "text", required: false },
    { id: "personal_address", question: "כתובת מגורים:", type: "text", required: true },
    { id: "personal_unit", question: "יחידה:", type: "text", required: true },
    { id: "personal_currentRole", question: "תפקיד נוכחי:", type: "text", required: true },
    { id: "personal_roleTime", question: "וותק בתפקיד (יחידות זמן):", type: "text", required: true },
    { id: "personal_futureRole", question: "תפקיד עתידי (אם ידוע):", type: "text", required: false },
    { id: "personal_phone", question: "טלפון:", type: "text", required: true },
    { id: "personal_mobile", question: "טלפון נייד:", type: "text", required: true },
    { id: "personal_militaryMobile", question: "מייל צבאי (כן/לא):", type: "text", required: false },
    { id: "personal_commanderName", question: "שם המפקד:", type: "text", required: true },
    
    // === 2. 📚 רקע וחינוך ===
    { id: "personal_medicalProfile", question: "פרופיל רפואי (ומגבלות):", type: "text", required: false },
    { id: "personal_educationalBackground", question: "רקע לימודי:", type: "textarea", required: false },
    { id: "personal_militaryBackground", question: "רקע צבאי:", type: "textarea", required: false },
    
    // === 3. 🎯 רקע אישי וכושר גופני ===
    {
        id: "personalBackground",
        question: "רקע אישי: לספר על משפחת המוצא, מקום לידתך, מקום מגוריך בילדותך ועוד:",
        type: "textarea",
        required: true
    },
    {
        id: "strengths",
        question: "מהן 3 נקודות החוזק העיקריות שלך? (טקסט חופשי)",
        type: "textarea",
        required: true
    },
    {
        id: "weaknesses",
        question: "מהן 3 נקודות החולשה העיקריות שלך שאתה רוצה לשפר? (טקסט חופשי)",
        type: "textarea",
        required: true
    },
    {
        id: "hasSubordinates",
        question: "האם יש לך פקודים?",
        type: "radio",
        options: ["כן", "לא"],
        required: true
    },
    {
        id: "subordinatesCount",
        question: "אם כן, כמה פקודים יש לך?",
        type: "number",
        required: false
    },
    {
        id: "roleSummary",
        question: "ציין את עיקרי התפקיד שלך ביחידה:",
        type: "textarea",
        required: true
    },
    {
        id: "medicalStatus",
        question: "מצב רפואי (ורגישות מיוחדות לשונות / תנאי תזונה):",
        type: "textarea",
        required: false
    },
    {
        id: "fitnessLevel",
        question: "מהי רמת הכושר הגופני / ספורטיבי שלך?",
        type: "select",
        options: ["מצוין", "מעולה", "טוב מאוד", "טוב", "בינוני", "לא בכושר", "לא בכושר בכלל"],
        required: true
    },
    {
        id: "sportShirtSize",
        question: "מידת חולצת ספורט (נא לציין גזרה/שרוול אם רלוונטי):",
        type: "text",
        required: true
    },
    // *** דילוג על שאלות הרכב כפי שביקשת ***

    // === 4. 💡 שירות צבאי ותחביבים (שאלות היכרות עומק) ===
    { id: "hobbies", question: "תחביבים ותחומי עניין:", type: "textarea", required: false },
    { id: "personalExperiences", question: "נושאים אישיים/חוויות שהורישו התנהגות סגל:", type: "textarea", required: false },
    { id: "whyMilitaryService", question: "מדוע הגעת לשירות צבאי?", type: "textarea", required: true },
    { id: "mostSignificantEvent", question: "מהו האירוע המשמעותי ביותר שחווית בשירותך בצה\"ל?", type: "textarea", required: true },
    { id: "whatILike", question: "מה אתה אוהב בשירותך / בתפקיד הצבאי?", type: "textarea", required: true },
    { id: "whatIDontLike", question: "מה אתה לא אוהב בשירותך / בתפקידך?", type: "textarea", required: true },
    { id: "influentialCommander", question: "ציין מפקד אחד שהשפיע עליך בשירותך, ומהן התכונות שגרמו לך להעריך אותו?", type: "textarea", required: true },
    
    // === 5. 🌟 יעדים וערכים ===
    { id: "courseGoals", question: "מהם היעדים האישיים שאתה מציב לעצמך בקורס?", type: "textarea", required: true },
    { id: "supremeValue", question: "מהו הערך העליון שעליו אינך מוכן להתפשר?", type: "text", required: true },
    { id: "unappreciatedTrait", question: "ציין תכונה אנושית אחת שאינך מעריך והסבר מדוע:", type: "textarea", required: true },
    { id: "roleModel", question: "מיהו המודל לחיקוי שלך ומדוע?", type: "textarea", required: true },
    { id: "initialImpression", question: "כשאנשים פוגשים אותך בפעם הראשונה, מה הם חושבים עליך? ובמה בטוח הם טועים לגביך?", type: "textarea", required: false },

    // === 6. 💬 ציפיות ונושאים נוספים ===
    { id: "candidateExpectations", question: "ציפיות מהמועמד: מהן הציפיות שלך מעצמך (כמועמד לקורס)?", type: "textarea", required: true },
    { id: "staffExpectations", question: "ציפיות מסגל התוכנית: מה הציפיות שלך מהמפקדים שלך בתפקיד ההכשרה?", type: "textarea", required: true },
    { id: "additionalNotes", question: "נושא נוסף שחשוב לך לציין ולא שאלנו:", type: "textarea", required: false },
];
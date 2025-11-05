// === הערה חשובה: ודא שקובץ questions.js נטען לפני קובץ זה ב-HTML ===

// ====== הגדרות גלובליות וקונפיגורציה ======
const API_URL = 'https://qr.bot4wa.com/kodkod/api.php'; // נתיב ה-API (HTTPS)
const MAX_LOGIN_ATTEMPTS = 5; 

// מצב הטופס
let currentStep = 0;
let loginAttempts = 0;
const formData = {}; // אובייקט לשמירת התשובות של המשתמש באופן זמני
let currentUserId = null; // מזהה המשתמש שהתקבל מהשרת לאחר אימות מוצלח
let formQuestions = []; // ייטען מהשרת
let questionsLoaded = false; // דגל לבדיקה האם השאלות נטענו

// Session management
let sessionRefreshInterval = null; // Timer for automatic session refresh
const SESSION_REFRESH_TIME = 25 * 60 * 1000; // Refresh every 25 minutes (before 30 min timeout)

// ====== אלמנטים DOM (אלמנטי ממשק) ======
const loginScreen = document.getElementById('login-screen');
const mainFormContainer = document.getElementById('main-form-container');
const tzInput = document.getElementById('tz-input');
const loginButton = document.getElementById('login-button');
const errorMessage = document.getElementById('error-message');
const formStepsContainer = document.getElementById('form-steps-container');
const prevButton = document.getElementById('prev-button');
const nextButton = document.getElementById('next-button');
const confirmButton = document.getElementById('confirm-button');

// ====== פונקציות API (תקשורת שרת) ======

/**
 * שולחת בקשה לשרת ה-PHP עם אימות מבוסס-Session.
 * @param {string} action - הפעולה בשרת (login, submit, get_questions).
 * @param {object} data - הנתונים לשליחה.
 */
async function sendApiRequest(action, data = {}) {
    const headers = {
        'Content-Type': 'application/json'
    };

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: headers,
            credentials: 'include', // שליחת cookies לאימות Session
            body: JSON.stringify({ action: action, ...data })
        });

        // אם השרת החזיר קוד שגיאה (כמו 401, 403), זורק שגיאה
        if (!response.ok) {
            const errorBody = await response.json();
            throw new Error(errorBody.message || 'שגיאה לא ידועה מהשרת');
        }

        return response.json();

    } catch (error) {
        console.error("API Request Failed:", error);
        return { success: false, message: error.message };
    }
}

/**
 * שומרת תשובה בודדת לשרת באופן אוטומטי
 * @param {string} questionId - מזהה השאלה
 * @param {string} answerValue - ערך התשובה
 */
async function autoSaveAnswer(questionId, answerValue) {
    try {
        const result = await sendApiRequest('auto_save', {
            question_id: questionId,
            answer_value: answerValue
        });

        if (result.success) {
            console.log(`תשובה לשאלה ${questionId} נשמרה אוטומטית.`);
        } else {
            console.warn(`שגיאה בשמירה אוטומטית: ${result.message}`);
        }

        return result.success;
    } catch (error) {
        console.error("Auto-save failed:", error);
        return false;
    }
}

/**
 * טוענת את השאלות מהמאגר
 */
async function loadQuestionsFromDatabase() {
    console.log("טוען שאלות מהמאגר...");

    try {
        const result = await sendApiRequest('get_questions');

        // New structure format (with text and condition blocks)
        if (result.success && result.use_structure && result.structure) {
            console.log("נטענה מבנה חדש עם בלוקים");
            formQuestions = convertStructureToSteps(result.structure);
            questionsLoaded = true;
            console.log(`נטענו ${formQuestions.length} שלבים בהצלחה.`);
            return formQuestions.length > 0;
        }
        // Old format (questions only)
        else if (result.success && result.questions && result.questions.length > 0) {
            formQuestions = result.questions;
            questionsLoaded = true;
            console.log(`נטענו ${result.total} שאלות בהצלחה מהמאגר.`);
            return true;
        } else if (result.success) {
            // API returned success but no data - might be empty form
            console.warn("הטופס ריק - אין שאלות או בלוקים");
            return false;
        } else {
            console.error("לא נמצאו שאלות במאגר או שגיאה בטעינה:", result.message || '');
            // Fallback - אם קיים questions.js כגיבוי
            if (typeof window.formQuestions !== 'undefined' && window.formQuestions.length > 0) {
                formQuestions = window.formQuestions;
                questionsLoaded = true;
                console.log("נטענו שאלות מקובץ הגיבוי questions.js");
                return true;
            }
            return false;
        }
    } catch (error) {
        console.error("שגיאה בטעינת שאלות:", error);
        // Fallback
        if (typeof window.formQuestions !== 'undefined' && window.formQuestions.length > 0) {
            formQuestions = window.formQuestions;
            questionsLoaded = true;
            console.log("נטענו שאלות מקובץ הגיבוי questions.js");
            return true;
        }
        return false;
    }
}

/**
 * Converts block structure to flat list of steps (questions and text blocks)
 * Conditions are evaluated dynamically and not included as steps
 */
function convertStructureToSteps(blocks) {
    const steps = [];
    const blockMap = {};

    // Create a map of all blocks by ID
    blocks.forEach(block => {
        blockMap[block.id] = block;
    });

    // Recursively process blocks
    function processBlock(block, sectionInfo = null) {
        if (block.type === 'section') {
            const section = {
                id: block.content.title || 'Section',
                name: block.content.title || 'קטגוריה',
                color: block.content.color || '#667eea'
            };

            // Process children
            if (block.children && block.children.length > 0) {
                block.children.forEach(childId => {
                    const childBlock = blockMap[childId];
                    if (childBlock) {
                        processBlock(childBlock, section);
                    }
                });
            }
        } else if (block.type === 'text') {
            // Add text block as a step
            steps.push({
                id: block.id,
                type: 'text',
                blockType: 'text',
                title: block.content.title || '',
                content: block.content.content || '',
                style: block.content.style || 'default',
                category: sectionInfo
            });
        } else if (block.type === 'question') {
            // Convert to question format
            const content = block.content;
            steps.push({
                id: block.id,
                question: content.questionText,
                type: content.typeCode || 'text',
                blockType: 'question',
                required: content.isRequired || false,
                options: content.options || null,
                category: sectionInfo
            });
        } else if (block.type === 'condition') {
            // Store condition info but don't add as a step
            // Conditions will be evaluated when rendering
            block.conditionalChildren = block.children || [];
            // Process conditional children separately - they'll be shown/hidden dynamically
            if (block.children && block.children.length > 0) {
                block.children.forEach(childId => {
                    const childBlock = blockMap[childId];
                    if (childBlock) {
                        // Mark as conditional
                        const conditionalStep = processBlockAsConditional(childBlock, block, sectionInfo);
                        if (conditionalStep) {
                            steps.push(conditionalStep);
                        }
                    }
                });
            }
        }
    }

    function processBlockAsConditional(block, conditionBlock, sectionInfo) {
        const baseStep = {};

        if (block.type === 'text') {
            Object.assign(baseStep, {
                id: block.id,
                type: 'text',
                blockType: 'text',
                title: block.content.title || '',
                content: block.content.content || '',
                style: block.content.style || 'default',
                category: sectionInfo,
                conditional: true,
                conditionData: conditionBlock.content
            });
        } else if (block.type === 'question') {
            const content = block.content;
            Object.assign(baseStep, {
                id: block.id,
                question: content.questionText,
                type: content.typeCode || 'text',
                blockType: 'question',
                required: content.isRequired || false,
                options: content.options || null,
                category: sectionInfo,
                conditional: true,
                conditionData: conditionBlock.content
            });
        }

        return baseStep;
    }

    // Process top-level blocks
    blocks.forEach(block => {
        if (!block.parentId) {
            processBlock(block);
        }
    });

    return steps;
}

// ====== Session Management ======

/**
 * Starts automatic session refresh timer
 * Refreshes session every 25 minutes to prevent 30 min timeout
 */
function startSessionRefresh() {
    console.log('Starting automatic session refresh (every 25 minutes)');

    // Clear any existing interval
    if (sessionRefreshInterval) {
        clearInterval(sessionRefreshInterval);
    }

    // Set up automatic refresh
    sessionRefreshInterval = setInterval(async () => {
        console.log('Auto-refreshing session...');
        await refreshSession();
    }, SESSION_REFRESH_TIME);
}

/**
 * Stops automatic session refresh
 */
function stopSessionRefresh() {
    if (sessionRefreshInterval) {
        clearInterval(sessionRefreshInterval);
        sessionRefreshInterval = null;
        console.log('Stopped session refresh');
    }
}

/**
 * Manually refresh session by pinging check_session
 */
async function refreshSession() {
    try {
        const result = await sendApiRequest('check_session');

        if (result.success && result.authenticated) {
            console.log('✓ Session refreshed successfully');
            return true;
        } else {
            console.warn('Session refresh failed - not authenticated');
            stopSessionRefresh();

            if (result.session_expired) {
                displayError('פג תוקף ההתחברות. התשובות שלך נשמרו. אנא התחבר מחדש.');
                setTimeout(() => {
                    showLoginScreen();
                }, 2000);
            }
            return false;
        }
    } catch (error) {
        console.error('Error refreshing session:', error);
        return false;
    }
}

// ====== פונקציות עזר ו-UI ======

/**
 * מציג הודעת שגיאה למשתמש
 * @param {string} message - הודעת השגיאה להצגה
 */
function displayError(message) {
    errorMessage.textContent = message;
    errorMessage.style.display = 'block';
}

/**
 * יוצרת את אלמנט ה-HTML עבור השאלה הנוכחית
 * @param {object} questionData - אובייקט השאלה מ-formQuestions
 */
function renderQuestion(questionData) {
    // ניקוי תוכן קודם
    formStepsContainer.innerHTML = '';

    // Check if this step should be shown (conditional logic)
    if (questionData.conditional && !evaluateCondition(questionData.conditionData)) {
        // Skip to next step automatically if condition not met
        if (currentStep < formQuestions.length - 1) {
            currentStep++;
            renderQuestion(formQuestions[currentStep]);
        }
        return;
    }

    // הוספת צבע רקע מלא לפי קטגוריה
    if (questionData.category && questionData.category.color) {
        const color = questionData.category.color;
        // החלת צבע רקע על כל המסך
        document.body.style.backgroundColor = color + '30'; // שקיפות 30%
        mainFormContainer.style.backgroundColor = color + '30';
    } else {
        // צבע ברירת מחדל
        document.body.style.backgroundColor = '#f5f7fa';
        mainFormContainer.style.backgroundColor = '#f5f7fa';
    }

    const stepDiv = document.createElement('div');
    stepDiv.className = 'form-step';

    // Handle text blocks differently from questions
    if (questionData.blockType === 'text') {
        renderTextBlock(questionData, stepDiv);
        formStepsContainer.appendChild(stepDiv);
        // For text blocks, enable next button immediately
        nextButton.disabled = false;
        return;
    }

    // Regular question rendering below

    const questionText = document.createElement('h2');
    questionText.textContent = questionData.question;
    stepDiv.appendChild(questionText);

    let inputElement;

    switch (questionData.type) {
        case 'textarea':
            inputElement = document.createElement('textarea');
            inputElement.rows = 5;
            inputElement.placeholder = 'הקלד/י כאן את התשובה המפורטת...';
            break;
        case 'select':
            inputElement = document.createElement('select');
            inputElement.innerHTML = '<option value="" disabled selected>בחר אפשרות...</option>';
            if (questionData.options && Array.isArray(questionData.options)) {
                questionData.options.forEach(optionText => {
                    const option = document.createElement('option');
                    option.value = optionText;
                    option.textContent = optionText;
                    inputElement.appendChild(option);
                });
            } else {
                console.error(`Question ${questionData.id} is type 'select' but missing options array`);
            }
            break;
        case 'radio':
            inputElement = document.createElement('div');
            inputElement.className = 'radio-group';
            if (questionData.options && Array.isArray(questionData.options)) {
                questionData.options.forEach((optionText, index) => {
                    const radioId = `${questionData.id}-${index}`;

                    const radioInput = document.createElement('input');
                    radioInput.type = 'radio';
                    radioInput.id = radioId;
                    radioInput.name = questionData.id; // Name חובה לקבוצת רדיו
                    radioInput.value = optionText;

                    const radioLabel = document.createElement('label');
                    radioLabel.htmlFor = radioId;
                    radioLabel.textContent = optionText;

                    inputElement.appendChild(radioInput);
                    inputElement.appendChild(radioLabel);
                });
            } else {
                console.error(`Question ${questionData.id} is type 'radio' but missing options array`);
            }
            break;
        case 'checkbox':
            inputElement = document.createElement('div');
            inputElement.className = 'checkbox-group';
            if (questionData.options && Array.isArray(questionData.options)) {
                questionData.options.forEach((optionText, index) => {
                    const checkboxId = `${questionData.id}-${index}`;

                    const checkboxInput = document.createElement('input');
                    checkboxInput.type = 'checkbox';
                    checkboxInput.id = checkboxId;
                    checkboxInput.name = questionData.id;
                    checkboxInput.value = optionText;

                    const checkboxLabel = document.createElement('label');
                    checkboxLabel.htmlFor = checkboxId;
                    checkboxLabel.textContent = optionText;

                    inputElement.appendChild(checkboxInput);
                    inputElement.appendChild(checkboxLabel);
                });
            } else {
                console.error(`Question ${questionData.id} is type 'checkbox' but missing options array`);
            }
            break;
        case 'number':
            inputElement = document.createElement('input');
            inputElement.type = 'number';
            inputElement.placeholder = 'הזן מספר...';
            break;
        case 'text':
        default:
            inputElement = document.createElement('input');
            inputElement.type = 'text';
            inputElement.placeholder = 'הקלד/י תשובה קצרה...';
            break;
    }

    // הוספת attributes לשדות קלט שאינם radio או checkbox
    if (questionData.type !== 'radio' && questionData.type !== 'checkbox' && inputElement) {
        inputElement.id = questionData.id;
        inputElement.name = questionData.id;
        inputElement.required = questionData.required || false;
        stepDiv.appendChild(inputElement);
    } else {
         stepDiv.appendChild(inputElement);
    }

    formStepsContainer.appendChild(stepDiv);

    // טוען ערך קודם אם קיים (מה-DB או מהזיכרון הזמני)
    const savedValue = formData[questionData.id];
    if (savedValue) {
         if (questionData.type === 'radio') {
            const radio = stepDiv.querySelector(`input[value="${savedValue}"]`);
            if (radio) radio.checked = true;
         } else if (questionData.type === 'checkbox') {
            // עבור checkbox, הערך יכול להיות מחרוזת JSON או מערך
            let selectedValues = [];
            if (typeof savedValue === 'string') {
                try {
                    selectedValues = JSON.parse(savedValue);
                } catch (e) {
                    selectedValues = [savedValue]; // אם זה לא JSON, מטפלים כערך יחיד
                }
            } else if (Array.isArray(savedValue)) {
                selectedValues = savedValue;
            }
            // סימון התיבות המתאימות
            selectedValues.forEach(value => {
                const checkbox = stepDiv.querySelector(`input[value="${value}"]`);
                if (checkbox) checkbox.checked = true;
            });
         } else if (inputElement && inputElement.tagName !== 'DIV') { // div זה קבוצת ה-radio/checkbox
             inputElement.value = savedValue;
         }
    }
}

/**
 * Renders a text block (informational content, not a question)
 */
function renderTextBlock(textData, container) {
    const styleMap = {
        'default': { bg: '#f9fafb', border: '#e5e7eb', icon: '📝' },
        'info': { bg: '#eff6ff', border: '#3b82f6', icon: 'ℹ️' },
        'warning': { bg: '#fffbeb', border: '#f59e0b', icon: '⚠️' },
        'success': { bg: '#f0fdf4', border: '#10b981', icon: '✓' }
    };

    const style = styleMap[textData.style] || styleMap['default'];

    const textBlock = document.createElement('div');
    textBlock.style.cssText = `
        background: ${style.bg};
        border-left: 4px solid ${style.border};
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    `;

    if (textData.title) {
        const title = document.createElement('h3');
        title.style.cssText = 'margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;';
        title.innerHTML = `<span>${style.icon}</span><span>${textData.title}</span>`;
        textBlock.appendChild(title);
    }

    const content = document.createElement('div');
    content.style.cssText = 'line-height: 1.6; white-space: pre-wrap;';
    content.textContent = textData.content;
    textBlock.appendChild(content);

    container.appendChild(textBlock);
}

/**
 * Evaluates a condition based on form data
 */
function evaluateCondition(conditionData) {
    if (!conditionData || !conditionData.questionId) {
        return true; // If no valid condition, show by default
    }

    const questionId = conditionData.questionId;
    const operator = conditionData.operator;
    const expectedValue = conditionData.value;
    const actualValue = formData[questionId];

    // If no answer yet, condition is false
    if (actualValue === undefined || actualValue === null || actualValue === '') {
        return operator === 'is_empty';
    }

    // Convert to string for comparison
    const actual = String(actualValue).toLowerCase();
    const expected = String(expectedValue).toLowerCase();

    switch (operator) {
        case 'equals':
            return actual === expected;
        case 'not_equals':
            return actual !== expected;
        case 'contains':
            return actual.includes(expected);
        case 'greater':
            return parseFloat(actualValue) > parseFloat(expectedValue);
        case 'less':
            return parseFloat(actualValue) < parseFloat(expectedValue);
        case 'is_empty':
            return !actualValue;
        case 'is_not_empty':
            return !!actualValue;
        default:
            return true;
    }
}

/**
 * שומרת את תשובת השלב הנוכחי לאובייקט הזמני
 */
function saveCurrentAnswer() {
    const questionData = formQuestions[currentStep];

    // Skip saving for text blocks (they're not questions)
    if (questionData.blockType === 'text') {
        return;
    }

    let value = '';

    if (questionData.type === 'radio') {
        const checkedRadio = formStepsContainer.querySelector(`input[name="${questionData.id}"]:checked`);
        value = checkedRadio ? checkedRadio.value.trim() : '';
    } else if (questionData.type === 'checkbox') {
        // עבור checkbox, אוסף את כל התיבות המסומנות למערך
        const checkedBoxes = formStepsContainer.querySelectorAll(`input[name="${questionData.id}"]:checked`);
        const selectedValues = Array.from(checkedBoxes).map(cb => cb.value.trim());
        value = selectedValues.length > 0 ? selectedValues : '';
    } else {
        const input = document.getElementById(questionData.id);
        value = input ? input.value.trim() : '';
    }

    if (value && (Array.isArray(value) ? value.length > 0 : true)) {
        formData[questionData.id] = value;
    } else {
        delete formData[questionData.id]; // מסיר אם מרוקן
    }
    // מעדכן רק את מצב הכפתורים, ללא re-render של השאלה
    updateButtonStates();
}

/**
 * מעדכנת רק את מצב הכפתורים ללא re-render של השאלה
 */
function updateButtonStates() {
    if (formQuestions.length === 0) return;

    // עדכון כפתור 'הקודם'
    prevButton.disabled = currentStep === 0;

    // הגדרת מצב כפתור 'הבא' ו'אישור'
    const isLastStep = currentStep === formQuestions.length - 1;
    nextButton.style.display = isLastStep ? 'none' : 'inline-flex';
    confirmButton.style.display = isLastStep ? 'inline-flex' : 'none';

    // כפתור 'הבא' יהיה פעיל רק אם יש תשובה לשדה הנוכחי
    const currentQuestionId = formQuestions[currentStep].id;
    // אם השדה אינו נדרש, הכפתור תמיד פעיל
    if (!formQuestions[currentStep].required) {
        nextButton.disabled = false;
    } else {
        nextButton.disabled = !formData[currentQuestionId];
    }
}

/**
 * מעדכנת את מצב הניווט (כפתורים, מספר השלב)
 */
function updateNavigation() {
    if (formQuestions.length === 0) return;

    // עדכון כפתורים
    updateButtonStates();

    // עדכון סרגל ההתקדמות
    updateProgress();

    // מציג את השאלה הנוכחית
    renderQuestion(formQuestions[currentStep]);
}

/**
 * מעדכנת את סרגל ההתקדמות
 */
function updateProgress() {
    const progressFill = document.getElementById('progress-fill');
    const progressCurrent = document.getElementById('progress-current');
    const progressTotal = document.getElementById('progress-total');
    const categoryName = document.getElementById('category-name');

    if (progressFill && progressCurrent && progressTotal) {
        const totalSteps = formQuestions.length;
        const currentStepNum = currentStep + 1;
        const percentage = (currentStepNum / totalSteps) * 100;

        progressFill.style.width = percentage + '%';
        progressCurrent.textContent = currentStepNum;
        progressTotal.textContent = totalSteps;

        // עדכון שם הקטגוריה
        const currentQuestion = formQuestions[currentStep];
        if (categoryName && currentQuestion && currentQuestion.category) {
            categoryName.textContent = currentQuestion.category.name;
            // החלת צבע הקטגוריה על סרגל ההתקדמות
            progressFill.style.backgroundColor = currentQuestion.category.color;
        } else {
            if (categoryName) categoryName.textContent = '';
        }
    }
}

// ====== טיפול באירועים (Event Handlers) ======

// 1. כניסה
loginButton.addEventListener('click', async () => {
    const tz = tzInput.value.trim();
    errorMessage.style.display = 'none';

    // Validate: must be 7 or 9 digits
    if ((tz.length !== 7 && tz.length !== 9) || isNaN(tz)) {
        loginAttempts++;
        displayError(`מספר זהוי לא תקין. הזן 7 או 9 ספרות. נותרו לך ${MAX_LOGIN_ATTEMPTS - loginAttempts} ניסיונות.`);
        if (loginAttempts >= MAX_LOGIN_ATTEMPTS) {
            loginButton.disabled = true;
        }
        return;
    }
    
    // שליחת בקשה לשרת PHP
    const result = await sendApiRequest('login', { tz: tz });

    if (result.success) {
        console.log("אימות מוצלח!");

        // Check if there's a redirect specified (new behavior: go to dashboard)
        if (result.redirect_to) {
            console.log("מפנה ללוח המשימות...");
            window.location.href = result.redirect_to;
            return;
        }

        // Legacy behavior: load form directly (for backward compatibility)
        console.log("טוען שאלות...");

        // שמירת ה-user_id שהתקבל מהשרת
        currentUserId = result.user_id;

        // טעינת נתונים קודמים (שדות שמולאו כבר)
        Object.assign(formData, result.previous_data || {});

        // טעינת השאלות מהמאגר
        const questionsLoadSuccess = await loadQuestionsFromDatabase();

        if (!questionsLoadSuccess) {
            displayError('שגיאה בטעינת השאלות. אנא נסה שוב.');
            return;
        }

        loginScreen.style.display = 'none';
        mainFormContainer.style.display = 'flex';

        // Start automatic session refresh (every 25 minutes)
        startSessionRefresh();

        // קפיצה לשאלה הראשונה שאין לה תשובה
        currentStep = result.first_unanswered_index || 0;
        console.log(`מתחיל משלב ${currentStep + 1} (שאלה ראשונה ללא תשובה)`);
        updateNavigation();

    } else {
        // שגיאות מהשרת: ת.ז. שגויה, חסימה וכו'.
        displayError(result.message);
        if (result.message.includes('חסומה')) { // אם המשתמש חסום
             loginButton.disabled = true;
        }
    }
});

// 2. מעבר לשלב הבא
nextButton.addEventListener('click', async () => {
    saveCurrentAnswer(); // שומר את התשובה של המסך הנוכחי לזיכרון מקומי

    // שמירה אוטומטית לשרת (skip text blocks)
    const questionData = formQuestions[currentStep];

    if (questionData.blockType === 'question') {
        const answerValue = formData[questionData.id] || '';
        if (answerValue) {
            await autoSaveAnswer(questionData.id, answerValue);
        }
    }

    if (currentStep < formQuestions.length - 1) {
        currentStep++;
        updateNavigation();
    }
});

// 3. מעבר לשלב הקודם
prevButton.addEventListener('click', () => {
    saveCurrentAnswer(); // שומר את התשובה של המסך הנוכחי
    if (currentStep > 0) {
        currentStep--;
        updateNavigation();
    }
});

// 4. כפתור אישור (שליחה סופית)
confirmButton.addEventListener('click', async () => {
    saveCurrentAnswer(); // שומר את התשובה האחרונה

    if (!currentUserId) {
        alert("שגיאה: אימות משתמש לא בוצע.");
        return;
    }

    // בדיקה אחרונה על שדות חובה לפני שליחה
    const requiredQuestions = formQuestions.filter(q => q.required);
    const missingFields = requiredQuestions.filter(q => !formData[q.id]);

    if (missingFields.length > 0) {
        alert(`חובה למלא את כל שדות החובה. חסרים ${missingFields.length} שאלות.`);
        // אפשר להפנות לשאלה החסרה הראשונה:
        const firstMissingIndex = formQuestions.findIndex(q => !formData[q.id] && q.required);
        if (firstMissingIndex !== -1) {
            currentStep = firstMissingIndex;
            updateNavigation();
        }
        return;
    }

    const submissionData = {
        form_data: formData
        // user_id לא נדרש יותר - השרת מקבל אותו מה-Session
    };

    // שליחת בקשה לשרת PHP
    const result = await sendApiRequest('submit', submissionData);

    if (result.success) {
        alert("הטופס נשמר בהצלחה! תודה רבה.");
        // ניתן להפנות לדף תודה או להציג הודעה
        mainFormContainer.innerHTML = '<h1>תודה רבה!</h1><p>הטופס נשלח ונשמר בהצלחה.</p>';
        document.getElementById('form-footer').style.display = 'none';
    } else {
        alert(`שגיאה בשליחה: ${result.message}`);
    }
});


// 5. ניטור שינויים בשדה הקלט הנוכחי (לעדכון כפתור 'הבא')
// עבור שדות שאינם radio (כי radio דורש event listener אחר)
formStepsContainer.addEventListener('input', () => {
    saveCurrentAnswer();
});
// עבור שדות radio ו-checkbox
formStepsContainer.addEventListener('change', (e) => {
    if (e.target.type === 'radio' || e.target.type === 'checkbox') {
         saveCurrentAnswer();
    }
});

// 6. טיפול במקש Enter (כפתור ה-V בטלפון)
formStepsContainer.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        // בדיקה אם השדה הנוכחי הוא textarea (תשובה ארוכה)
        const activeElement = document.activeElement;

        // אם זה textarea, מאפשרים התנהגות רגילה (ירידת שורה)
        if (activeElement && activeElement.tagName === 'TEXTAREA') {
            return; // לא עושים כלום, מאפשרים ירידת שורה
        }

        // לשדות אחרים - מעבר לשאלה הבאה
        e.preventDefault(); // מונע שליחת טופס ברירת מחדל

        const isLastStep = currentStep === formQuestions.length - 1;

        if (isLastStep) {
            // בשלב האחרון - לחץ על כפתור אישור
            confirmButton.click();
        } else {
            // בשלבים אחרים - לחץ על כפתור הבא (אם פעיל)
            if (!nextButton.disabled) {
                nextButton.click();
            }
        }
    }
});


// הסרנו את בדיקת המכשיר הנייד - המערכת פתוחה לכולם
// העיצוב מותאם למובייל אבל ניתן לגשת גם ממחשב

// 7. טיפול בגלילה כאשר מקלדת נפתחת (מובייל)
formStepsContainer.addEventListener('focusin', (e) => {
    // כאשר שדה קלט מקבל פוקוס, גלול כך שסרגל ההתקדמות והשאלה יהיו נראים
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT')) {
        // המתן רגע קצר עד שהמקלדת נפתחת
        setTimeout(() => {
            // גלול את סרגל ההתקדמות לראש המסך
            const progressContainer = document.querySelector('.progress-container');
            if (progressContainer) {
                progressContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }
});

// 8. בדיקת סשן קיים בטעינת הדף
async function checkExistingSession() {
    try {
        const result = await sendApiRequest('check_session');

        if (result.success && result.authenticated) {
            console.log("נמצא session קיים - טוען את הטופס...");

            // שמירת מזהה המשתמש
            currentUserId = result.user_id;

            // טעינת נתונים קודמים
            Object.assign(formData, result.previous_data || {});

            // טעינת השאלות מהמאגר
            const questionsLoadSuccess = await loadQuestionsFromDatabase();

            if (!questionsLoadSuccess) {
                console.error('שגיאה בטעינת השאלות.');
                showLoginScreen();
                return;
            }

            // הצגת הטופס במקום מסך הכניסה
            loginScreen.style.display = 'none';
            mainFormContainer.style.display = 'flex';

            // Start automatic session refresh (every 25 minutes)
            startSessionRefresh();

            // קפיצה לשאלה הראשונה שאין לה תשובה
            currentStep = result.first_unanswered_index || 0;
            console.log(`ממשיך משלב ${currentStep + 1}`);
            updateNavigation();

        } else {
            // Session expired or not authenticated
            if (result.session_expired) {
                console.log('פג תוקף ההתחברות - התשובות שלך נשמרו');
                displayError('פג תוקף ההתחברות. התשובות שלך נשמרו במערכת. אנא התחבר מחדש להמשך.');
            }
            showLoginScreen();
        }
    } catch (error) {
        console.error("שגיאה בבדיקת session:", error);
        showLoginScreen();
    }
}

function showLoginScreen() {
    if (loginScreen) loginScreen.style.display = 'flex';
    if (mainFormContainer) mainFormContainer.style.display = 'none';
}

// 8. אתחול ראשוני - בדיקת session קיים
document.addEventListener('DOMContentLoaded', () => {
    checkExistingSession();
});
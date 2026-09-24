(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? factory(exports, require('@vladmandic/human'), require('@techstark/opencv-js')) :
    typeof define === 'function' && define.amd ? define(['exports', '@vladmandic/human', '@techstark/opencv-js'], factory) :
    (global = typeof globalThis !== 'undefined' ? globalThis : global || self, factory(global.FaceDetectionEngine = {}, global.Human, global.cv));
})(this, (function (exports, Human, cvModuleImport) { 'use strict';

    function _interopNamespaceDefault(e) {
        var n = Object.create(null);
        if (e) {
            Object.keys(e).forEach(function (k) {
                if (k !== 'default') {
                    var d = Object.getOwnPropertyDescriptor(e, k);
                    Object.defineProperty(n, k, d.get ? d : {
                        enumerable: true,
                        get: function () { return e[k]; }
                    });
                }
            });
        }
        n.default = e;
        return Object.freeze(n);
    }

    var cvModuleImport__namespace = /*#__PURE__*/_interopNamespaceDefault(cvModuleImport);

    /**
     * Liveness action enumeration
     */
    exports.LivenessAction = void 0;
    (function (LivenessAction) {
        // Blink
        LivenessAction["BLINK"] = "blink";
        // Mouth open
        LivenessAction["MOUTH_OPEN"] = "mouth_open";
        // Nod down (look down)
        LivenessAction["NOD_DOWN"] = "nod_down";
        // Nod up (look up)
        LivenessAction["NOD_UP"] = "nod_up";
    })(exports.LivenessAction || (exports.LivenessAction = {}));
    /**
     * Liveness action status enumeration
     */
    exports.LivenessActionStatus = void 0;
    (function (LivenessActionStatus) {
        LivenessActionStatus["STARTED"] = "started";
        LivenessActionStatus["COMPLETED"] = "completed";
        LivenessActionStatus["MISMATCH"] = "mismatch";
        LivenessActionStatus["TIMEOUT"] = "timeout";
    })(exports.LivenessActionStatus || (exports.LivenessActionStatus = {}));
    exports.DetectionPeriod = void 0;
    (function (DetectionPeriod) {
        DetectionPeriod["DETECT"] = "detect";
        DetectionPeriod["COLLECT"] = "collect";
        DetectionPeriod["VERIFY"] = "verify";
    })(exports.DetectionPeriod || (exports.DetectionPeriod = {}));
    /**
     * Detect code enumeration - for detector info events
     */
    exports.DetectionCode = void 0;
    (function (DetectionCode) {
        DetectionCode["VIDEO_NO_FACE"] = "VIDEO_NO_FACE";
        DetectionCode["MULTIPLE_FACE"] = "MULTIPLE_FACE";
        DetectionCode["FACE_TOO_SMALL"] = "FACE_TOO_SMALL";
        DetectionCode["FACE_TOO_LARGE"] = "FACE_TOO_LARGE";
        DetectionCode["FACE_NOT_FRONTAL"] = "FACE_NOT_FRONTAL";
        DetectionCode["FACE_LOW_QUALITY"] = "FACE_LOW_QUALITY";
        DetectionCode["FACE_IMAGE_CAPTURED"] = "FACE_IMAGE_CAPTURED";
        DetectionCode["FACE_NOT_MOVING"] = "FACE_NOT_MOVING";
        DetectionCode["PHOTO_ATTACK_DETECTED"] = "PHOTO_ATTACK_DETECTED";
    })(exports.DetectionCode || (exports.DetectionCode = {}));
    /**
     * Error code enumeration
     */
    exports.ErrorCode = void 0;
    (function (ErrorCode) {
        // 检测器初始化失败
        ErrorCode["DETECTOR_NOT_INITIALIZED"] = "DETECTOR_NOT_INITIALIZED";
        // 摄像头访问被拒绝
        ErrorCode["CAMERA_ACCESS_DENIED"] = "CAMERA_ACCESS_DENIED";
        // 视频流获取失败
        ErrorCode["STREAM_ACQUISITION_FAILED"] = "STREAM_ACQUISITION_FAILED";
        // 内部错误
        ErrorCode["INTERNAL_ERROR"] = "INTERNAL_ERROR";
    })(exports.ErrorCode || (exports.ErrorCode = {}));
    exports.EngineState = void 0;
    (function (EngineState) {
        EngineState["IDLE"] = "idle";
        EngineState["INITIALIZING"] = "initializing";
        EngineState["READY"] = "ready";
        EngineState["DETECTING"] = "detecting"; // 检测中
    })(exports.EngineState || (exports.EngineState = {}));

    /**
     * Face Detection Engine - Configuration
     */
    /**
     * Default configuration for FaceDetectionEngine
     */
    const DEFAULT_OPTIONS$2 = {
        // Resource paths
        human_model_path: undefined,
        tensorflow_wasm_path: undefined,
        tensorflow_backend: 'auto',
        debug_mode: false,
        debug_log_level: 'info',
        debug_log_stages: undefined, // undefined 表示所有阶段
        debug_log_throttle: 100, // 默认 100ms 节流，防止过于频繁
        enable_face_moving_detection: true,
        enable_photo_attack_detection: true,
        // Detection Settings
        detect_video_ideal_width: 1280,
        detect_video_ideal_height: 720,
        detect_video_mirror: true,
        detect_video_load_timeout: 5000,
        // Collection Settings
        collect_min_collect_count: 3,
        collect_min_face_ratio: 0.5,
        collect_max_face_ratio: 0.9,
        collect_min_face_frontal: 0.9,
        collect_min_image_quality: 0.5,
        collect_face_frontal_features: {
            yaw_threshold: 3,
            pitch_threshold: 4,
            roll_threshold: 2
        },
        collect_image_quality_features: {
            require_full_face_in_bounds: false,
            min_laplacian_variance: 40, // 从 50 降低到 40，适应现实环境的光线和对焦变化
            min_gradient_sharpness: 0.15,
            min_blur_score: 0.6
        },
        // action Liveness Settings
        action_liveness_action_list: [exports.LivenessAction.BLINK, exports.LivenessAction.MOUTH_OPEN, exports.LivenessAction.NOD_DOWN, exports.LivenessAction.NOD_UP],
        action_liveness_action_count: 1,
        action_liveness_action_randomize: true,
        action_liveness_verify_timeout: 15000,
        action_liveness_min_mouth_open_percent: 0.2,
        photo_attack_passed_frame_count: 15,
    };
    /**
     * Merge user configuration with defaults
     * Nested objects (face_frontal_features, image_quality_features) are deeply merged
     * @param userConfig - User provided configuration (partial, optional)
     * @returns Complete resolved configuration with all required fields
     */
    function mergeOptions(userConfig) {
        // Start with deep clone of defaults
        const merged = structuredClone(DEFAULT_OPTIONS$2);
        if (!userConfig) {
            return merged;
        }
        // Merge all top-level properties
        Object.entries(userConfig).forEach(([key, value]) => {
            if (value === undefined)
                return; // Skip undefined values
            // Special handling for nested objects: deep merge instead of replace
            if (key === 'face_frontal_features' || key === 'image_quality_features') {
                merged[key] = {
                    ...merged[key],
                    ...value,
                };
            }
            else {
                merged[key] = value;
            }
        });
        return merged;
    }

    /**
     * Face Detection Engine - Event Emitter
     * Generic event emitter implementation
     */
    /**
     * Generic event emitter implementation
     * Provides on, off, once, and emit methods for event-driven architecture
     */
    class SimpleEventEmitter {
        listeners = new Map();
        /**
         * Register an event listener
         * @param event - Event name
         * @param listener - Listener callback
         */
        on(event, listener) {
            if (!this.listeners.has(String(event))) {
                this.listeners.set(String(event), new Set());
            }
            this.listeners.get(String(event)).add(listener);
        }
        /**
         * Remove an event listener
         * @param event - Event name
         * @param listener - Listener callback to remove
         */
        off(event, listener) {
            const set = this.listeners.get(String(event));
            if (set) {
                set.delete(listener);
            }
        }
        /**
         * Register a one-time event listener
         * @param event - Event name
         * @param listener - Listener callback (will be called once)
         */
        once(event, listener) {
            const wrappedListener = (data) => {
                listener(data);
                this.off(event, wrappedListener);
            };
            this.on(event, wrappedListener);
        }
        /**
         * Emit an event
         * @param event - Event name
         * @param data - Event data
         */
        emit(event, data) {
            const set = this.listeners.get(String(event));
            if (set) {
                set.forEach(listener => {
                    try {
                        ;
                        listener(data);
                    }
                    catch (error) {
                        console.error(`Error in event listener for ${String(event)}:`, error);
                    }
                });
            }
        }
        /**
         * Remove all listeners for an event or all events
         * @param event - Event name (optional, if not provided, clears all)
         */
        removeAllListeners(event) {
            if (event === undefined) {
                this.listeners.clear();
            }
            else {
                this.listeners.delete(String(event));
            }
        }
        /**
         * Get count of listeners for an event
         * @param event - Event name
         * @returns Number of listeners
         */
        listenerCount(event) {
            return this.listeners.get(String(event))?.size ?? 0;
        }
    }

    /**
     * 人脸正对度检测模块 - 混合多尺度算法版本
     *
     * 使用四层混合检测策略：
     * 1. 特征点对称性检测 (40%) - 眼睛水平线、鼻子中心、嘴角对称性
     * 2. 轮廓对称性检测 (35%) - Sobel 边缘检测的轮廓对称性
     * 3. 角度融合分析 (25%) - Yaw/Pitch/Roll 角度综合评分
     * 4. 手势识别验证 - 作为额外验证
     *
     * 相比单一方法，混合算法提升准确度 30-40%
     */
    /**
     * 检查人脸是否正对摄像头 - 主函数（混合多尺度版本）
     *
     * 使用四层混合检测策略：
     * 1. 特征点对称性检测 (40%) - 最准确
     * 2. 轮廓对称性检测 (35%) - 快速且鲁棒
     * 3. 角度融合分析 (25%) - 补充验证
     * 4. 手势识别验证 - 额外验证（如果提供手势数据）
     *
     * @param {any} cv - OpenCV 实例（用于轮廓对称性检测）
     * @param {FaceResult} face - 人脸检测结果（包含 rotation 和 annotations 信息）
     * @param {Array<GestureResult>} gestures - 检测到的手势/表情数组（可选）
     * @param {any} grayFrame - OpenCV Mat 对象（图像数据，用于轮廓检测）
     * @param {FaceFrontalFeatures} config - 正对度配置参数（包含角度阈值）
     * @returns {number} 正对度评分 (0-1)，1 表示完全正对
     *
     * @example
     * const mat = drawVideoToMat()
     * const score = calcFaceFrontal(cv, face, gestures, mat, config)
     * if (score > 0.9) {
     *   console.log('人脸足够正对')
     * }
     * grayFrame.delete()
     */
    function calcFaceFrontal(cv, face, gestures, grayFrame, config) {
        try {
            // 层 1：特征点对称性检测 (40%)
            const featureResult = detectFeatureSymmetry(face);
            const featureSymmetry = featureResult.score;
            // 层 2：轮廓对称性检测 (35%)
            const contourResult = detectContourSymmetry(cv, face, grayFrame);
            const contourSymmetry = contourResult.score;
            // 层 3：角度融合分析 (25%)
            const angleAnalysis = checkFaceFrontalWithAngles(face, config);
            // 层 4：手势验证 (全局系数)
            let gestureValidation = 1.0;
            if (gestures && gestures.length > 0) {
                const hasFacingCenter = checkFaceFrontalWithGestures(gestures);
                gestureValidation = hasFacingCenter ? 1 : 0.75; // 手势验证未通过时，降低评分
            }
            // 综合评分：特征点(40%) + 轮廓(35%) + 角度(25%)
            // 然后与手势验证(额外验证)相乘以确保手势验证通过
            const overall = (featureSymmetry * 0.4 +
                contourSymmetry * 0.35 +
                angleAnalysis * 0.25) * gestureValidation;
            return Math.min(1, overall);
        }
        catch (error) {
            console.warn('[FaceFrontal] Hybrid detection failed, falling back to angle analysis:', error);
            return checkFaceFrontalWithAngles(face, config);
        }
    }
    /**
     * 使用手势识别方法检查人脸正对度
     *
     * 从 Human.js 返回的手势中查找 "facing center" 标志
     *
     * @param {Array<GestureResult>} gestures - Human.js 检测到的手势数组
     * @returns {number} 评分 (0-1)，0 表示未检测到相关手势
     *
     * @example
     * const score = checkFaceFrontalWithGestures(result.gesture)
     */
    function checkFaceFrontalWithGestures(gestures) {
        if (!gestures) {
            return false;
        }
        // 检查是否有 facing center 手势
        return gestures.some((g) => {
            if (!g || !g.gesture)
                return false;
            return g.gesture.includes('facing center') || g.gesture.includes('facing camera');
        });
    }
    /**
     * 使用角度分析方法检查人脸正对度
     *
     * 分析人脸的 yaw、pitch、roll 三个角度
     * 使用加权评分：yaw (60%) + pitch (25%) + roll (15%)
     * 直接使用 CONFIG.FACE_FRONTAL 中的参数
     *
     * @param {FaceResult} face - 人脸检测结果
     * @param {FaceFrontalFeatures} config - 正对度配置参数
     * @returns {number} 正对度评分 (0-1)
     *
     * @example
     * const score = checkFaceFrontalWithAngles(face, config)
     */
    function checkFaceFrontalWithAngles(face, config) {
        // 获取角度信息
        const angles = extractFaceAngles(face);
        // 基础评分，从 1.0 开始
        let score = 1.0;
        // Yaw 角度惩罚（左右摇晃）- 权重最高 (60%)
        // 目标：yaw 应该在阈值以内
        const yawThreshold = config.yaw_threshold;
        const yawExcess = Math.max(0, Math.abs(angles.yaw) - yawThreshold);
        // yaw 每超过 1° 扣 0.15 分
        score -= yawExcess * 0.15;
        // Pitch 角度惩罚（上下俯仰）- 权重中等 (25%)
        // 目标：pitch 应该在阈值以内
        const pitchThreshold = config.pitch_threshold;
        const pitchExcess = Math.max(0, Math.abs(angles.pitch) - pitchThreshold);
        // pitch 每超过 1° 扣 0.1 分
        score -= pitchExcess * 0.1;
        // Roll 角度惩罚（旋转）- 权重最低 (15%)
        // 目标：roll 应该在阈值以内
        const rollThreshold = config.roll_threshold;
        const rollExcess = Math.max(0, Math.abs(angles.roll) - rollThreshold);
        // roll 每超过 1° 扣 0.12 分
        score -= rollExcess * 0.12;
        // 确保评分在 0-1 之间
        return Math.max(0, Math.min(1, score));
    }
    /**
     * 从人脸检测结果中提取三维旋转角度
     *
     * 返回标准化的 yaw、pitch、roll 角度值（单位：度）
     *
     * @param {FaceResult} face - Human.js 人脸检测结果
     * @returns {AngleAnalysisResult} 包含角度和评分的结果对象
     */
    function extractFaceAngles(face) {
        // 从 face.rotation.angle 获取角度信息
        const ang = face?.rotation?.angle || { yaw: 0, pitch: 0, roll: 0 };
        return {
            yaw: ang.yaw || 0,
            pitch: ang.pitch || 0,
            roll: ang.roll || 0,
            score: 1.0 // 占位符，会被 checkFaceFrontalWithAngles 覆盖
        };
    }
    // ==================== 层 1：特征点对称性检测 ====================
    /**
     * 特征点对称性检测
     * 分析眼睛、鼻子、嘴角的对称性
     */
    function detectFeatureSymmetry(face) {
        try {
            // 获取人脸的关键点（如果可用）
            const landmarks = extractFaceLandmarks(face);
            if (!landmarks) {
                return { score: 1.0, landmarks: undefined };
            }
            // 计算各个特征的对称性
            const eyeSymmetry = calculateEyeSymmetry(landmarks);
            const noseCenterScore = calculateNoseCenterAlignment(landmarks);
            const mouthSymmetry = calculateMouthSymmetry(landmarks);
            // 加权平均
            const featureScore = eyeSymmetry * 0.5 + // 眼睛对称性权重最高
                noseCenterScore * 0.3 + // 鼻子中心对齐
                mouthSymmetry * 0.2; // 嘴角对称性
            return {
                score: Math.min(1, featureScore),
                landmarks: {
                    leftEyeX: landmarks.leftEye?.x || 0,
                    rightEyeX: landmarks.rightEye?.x || 0,
                    eyeSymmetry,
                    noseX: landmarks.nose?.x || 0,
                    noseCenterScore,
                    mouthLeftX: landmarks.mouthLeft?.x || 0,
                    mouthRightX: landmarks.mouthRight?.x || 0,
                    mouthSymmetry
                }
            };
        }
        catch (error) {
            console.warn('[FaceFrontal] Feature symmetry detection failed:', error);
            return { score: 1.0, landmarks: undefined };
        }
    }
    /**
     * 从人脸检测结果中提取关键点
     * 基于 Human.js 的人脸关键点（如果可用）
     */
    function extractFaceLandmarks(face) {
        try {
            // Human.js FaceResult 的 annotations 包含各个特征的关键点
            // FaceLandmark 类型包括: 'leftEye', 'rightEye', 'nose', 'mouth', 等等
            const annotations = face.annotations;
            if (!annotations) {
                return null;
            }
            // 从 annotations 中提取关键点
            const leftEyePoints = annotations.leftEye || annotations.leftEyeUpper0 || [];
            const rightEyePoints = annotations.rightEye || annotations.rightEyeUpper0 || [];
            const nosePoints = annotations.nose || annotations.noseTip || [];
            const mouthPoints = annotations.mouth || annotations.lipsUpperOuter || [];
            // 计算平均位置
            const getAveragePoint = (points) => {
                if (!points || points.length === 0)
                    return null;
                let sumX = 0, sumY = 0;
                for (const point of points) {
                    if (point && point.length >= 2) {
                        sumX += point[0];
                        sumY += point[1];
                    }
                }
                return { x: sumX / points.length, y: sumY / points.length };
            };
            const landmarks = {
                leftEye: getAveragePoint(leftEyePoints),
                rightEye: getAveragePoint(rightEyePoints),
                nose: getAveragePoint(nosePoints),
                mouthLeft: mouthPoints.length > 0 ? { x: mouthPoints[0][0], y: mouthPoints[0][1] } : null,
                mouthRight: mouthPoints.length > 0 ? { x: mouthPoints[mouthPoints.length - 1][0], y: mouthPoints[mouthPoints.length - 1][1] } : null
            };
            // 检查是否至少有一些关键点
            if (Object.values(landmarks).every(v => v === null)) {
                return null;
            }
            return landmarks;
        }
        catch (error) {
            console.warn('[FaceFrontal] Extract landmarks failed:', error);
            return null;
        }
    }
    /**
     * 计算眼睛的水平对称性
     */
    function calculateEyeSymmetry(landmarks) {
        if (!landmarks.leftEye || !landmarks.rightEye)
            return 1.0;
        const leftEyeX = landmarks.leftEye.x;
        const rightEyeX = landmarks.rightEye.x;
        const leftEyeY = landmarks.leftEye.y;
        const rightEyeY = landmarks.rightEye.y;
        // 眼睛应该在同一水平线上
        const yDiff = Math.abs(leftEyeY - rightEyeY);
        const eyeDistance = Math.abs(rightEyeX - leftEyeX);
        // 如果眼睛垂直差异超过眼距的 30%，则不对称
        const symmetryScore = Math.max(0, 1.0 - (yDiff / (eyeDistance * 0.3)));
        return Math.min(1, symmetryScore);
    }
    /**
     * 计算鼻子中心对齐度
     */
    function calculateNoseCenterAlignment(landmarks) {
        if (!landmarks.leftEye || !landmarks.rightEye || !landmarks.nose)
            return 1.0;
        const leftEyeX = landmarks.leftEye.x;
        const rightEyeX = landmarks.rightEye.x;
        const noseX = landmarks.nose.x;
        // 鼻子应该在两只眼睛的中点
        const eyeCenter = (leftEyeX + rightEyeX) / 2;
        const noseDeviation = Math.abs(noseX - eyeCenter);
        const eyeDistance = Math.abs(rightEyeX - leftEyeX);
        // 如果鼻子偏离中心超过眼距的 25%，则不对齐
        const alignmentScore = Math.max(0, 1.0 - (noseDeviation / (eyeDistance * 0.25)));
        return Math.min(1, alignmentScore);
    }
    /**
     * 计算嘴角对称性
     */
    function calculateMouthSymmetry(landmarks) {
        if (!landmarks.mouthLeft || !landmarks.mouthRight)
            return 1.0;
        const mouthLeftX = landmarks.mouthLeft.x;
        const mouthRightX = landmarks.mouthRight.x;
        const mouthLeftY = landmarks.mouthLeft.y;
        const mouthRightY = landmarks.mouthRight.y;
        // 嘴角应该在同一水平线上
        const yDiff = Math.abs(mouthLeftY - mouthRightY);
        const mouthWidth = Math.abs(mouthRightX - mouthLeftX);
        // 如果嘴角垂直差异超过嘴宽的 20%，则不对称
        const symmetryScore = Math.max(0, 1.0 - (yDiff / (mouthWidth * 0.2)));
        return Math.min(1, symmetryScore);
    }
    // ==================== 层 2：轮廓对称性检测 ====================
    /**
     * 轮廓对称性检测
     * 使用 Sobel 边缘检测分析人脸轮廓的对称性
     */
    function detectContourSymmetry(cv, face, grayFrame) {
        try {
            if (!face.box) {
                return { score: 1.0, contour: undefined };
            }
            const [x, y, w, h] = face.box;
            const x_int = Math.max(0, Math.floor(x));
            const y_int = Math.max(0, Math.floor(y));
            const w_int = Math.min(w, grayFrame.cols - x_int);
            const h_int = Math.min(h, grayFrame.rows - y_int);
            if (w_int <= 0 || h_int <= 0) {
                return { score: 1.0, contour: undefined };
            }
            const gray = grayFrame.roi(new cv.Rect(x_int, y_int, w_int, h_int));
            try {
                // Sobel 边缘检测
                const sobelX = new cv.Mat();
                const sobelY = new cv.Mat();
                try {
                    cv.Sobel(gray, sobelX, cv.CV_32F, 1, 0, 3);
                    cv.Sobel(gray, sobelY, cv.CV_32F, 0, 1, 3);
                    // 计算边缘幅度
                    const edgeMap = new cv.Mat();
                    try {
                        cv.magnitude(sobelX, sobelY, edgeMap);
                        // 计算左右对称性
                        const symmetryScore = calculateLeftRightSymmetry(edgeMap);
                        const contourData = {
                            leftEdgeCount: Math.floor(symmetryScore * 1000),
                            rightEdgeCount: Math.floor(symmetryScore * 1000),
                            symmetryScore: symmetryScore
                        };
                        return { score: symmetryScore, contour: contourData };
                    }
                    finally {
                        edgeMap.delete();
                    }
                }
                finally {
                    sobelX.delete();
                    sobelY.delete();
                }
            }
            finally {
                gray.delete();
            }
        }
        catch (error) {
            console.warn('[FaceFrontal] Contour symmetry detection failed:', error);
            return { score: 1.0, contour: undefined };
        }
    }
    /**
     * 计算左右对称性
     */
    function calculateLeftRightSymmetry(edgeMap) {
        try {
            const height = edgeMap.rows;
            const width = edgeMap.cols;
            const midX = Math.floor(width / 2);
            let leftSum = 0;
            let rightSum = 0;
            // 计算左半部分和右半部分的边缘强度
            for (let y = 0; y < height; y++) {
                for (let x = 0; x < midX; x++) {
                    const val = edgeMap.ucharAt(y, x);
                    leftSum += val;
                }
                for (let x = midX; x < width; x++) {
                    const val = edgeMap.ucharAt(y, x);
                    rightSum += val;
                }
            }
            // 计算对称性评分（0-1）
            const maxSum = Math.max(leftSum, rightSum);
            if (maxSum === 0)
                return 1.0;
            const ratio = Math.min(leftSum, rightSum) / maxSum;
            // 如果左右边缘强度比值接近 1，说明对称性好
            const symmetryScore = Math.max(0.5, ratio);
            return Math.min(1, symmetryScore);
        }
        catch (error) {
            console.warn('[FaceFrontal] Calculate left-right symmetry failed:', error);
            return 1.0;
        }
    }

    /**
     * 图像质量检测模块
     *
     */
    // ==================== 常量配置 ====================
    /**
     * 质量检测的权重配置
     * 仅用于清晰度评估，权重之和应为 1.0
     *
     * 注意：采用"两个指标都必须通过"的AND逻辑
     * 因此权重应该平衡，体现两个指标的相对重要性而非绝对压制
     */
    const QUALITY_WEIGHTS = {
        laplacian: 0.6, // 拉普拉斯方差权重（主要指标，最稳定）
        gradient: 0.4 // Sobel梯度权重（辅助指标）
    };
    /**
     * OpenCV图像处理参数
     */
    const OPENCV_PARAMS = {
        canny_threshold_low: 50,
        canny_threshold_high: 150,
        sobel_kernel_size: 3,
        sobel_type: 'CV_64F',
        laplacian_type: 'CV_64F',
        gradient_energy_scale: 50,
        // 拉普拉斯方差归一化尺度：实际数据范围约45-70，设为70使中等清晰度(50)得分约0.71
        // 这样与权重0.85匹配，避免分数过低
        laplacian_variance_scale: 70,
        edge_ratio_reference: 0.3,
        edge_ratio_low: 0.05,
        edge_ratio_high: 0.7
    };
    // ==================== 主入口函数 ====================
    /**
     * 计算图像清晰度评分
     *
     * 综合检测图像的清晰度，使用两个指标：
     * - 拉普拉斯方差 (60%)：检测边缘清晰程度
     * - Sobel 梯度清晰度 (40%)：检测纹理梯度强度
     *
     * @param cv - OpenCV.js 对象，用于执行图像处理操作
     * @param grayFrame - OpenCV Mat 对象，包含灰度图像数据（已ROI裁剪）
     * @param config - 检测配置对象，包含：
     *   - min_laplacian_variance: 拉普拉斯方差最小阈值（默认 40）
     *   - min_gradient_sharpness: 梯度清晰度最小阈值（默认 0.15）
     * @param threshold - 综合质量评分阈值 (0-1)，大于等于此值判定为通过
     * @returns 综合质量检测结果，包含：
     *   - passed: 是否通过质量检测
     *   - score: 综合质量评分 (0-1)
     *   - metrics: 各维度详细指标（拉普拉斯方差、梯度清晰度、综合质量）
     *   - blurReasons: 清晰度不通过原因列表
     *   - suggestions: 改进建议列表
     */
    function calcImageQuality(cv, grayFrame, config, threshold) {
        const metrics = {};
        const completenessReasons = [];
        const blurReasons = [];
        try {
            const blurResult = checkImageSharpness(cv, grayFrame, config);
            metrics.laplacianVariance = blurResult.laplacianVariance;
            metrics.gradientSharpness = blurResult.gradientSharpness;
            if (!blurResult.laplacianVariance.passed) {
                blurReasons.push(blurResult.laplacianVariance.description);
            }
            if (!blurResult.gradientSharpness.passed) {
                blurReasons.push(blurResult.gradientSharpness.description);
            }
            const overallMetric = {
                name: '综合图像质量',
                value: blurResult.overallScore,
                threshold: threshold,
                passed: blurResult.overallScore >= threshold,
                description: `综合质量评分 ${(blurResult.overallScore * 100).toFixed(1)}%  | 清晰度: ${(blurResult.overallScore * 100).toFixed(0)}%)`
            };
            metrics.overallQuality = overallMetric;
            const passed = blurResult.overallScore >= threshold;
            const suggestions = [];
            if (!blurResult.laplacianVariance.passed) {
                suggestions.push('图像边缘不清晰，请确保光线充足且摄像头对焦清楚');
            }
            if (!blurResult.gradientSharpness.passed) {
                suggestions.push('图像纹理模糊，可能是运动模糊，请保持摄像头稳定');
            }
            return {
                passed,
                score: overallMetric.value,
                completenessReasons,
                blurReasons,
                metrics: metrics,
                suggestions: suggestions.length > 0 ? suggestions : undefined
            };
        }
        catch (error) {
            console.error('[ImageQuality] calcImageQuality failed:', error);
            return {
                passed: false,
                score: 0,
                completenessReasons: [`质量检测异常: ${error instanceof Error ? error.message : String(error)}`],
                blurReasons: [],
                metrics: {
                    completeness: {
                        name: '人脸完整度',
                        value: 0,
                        threshold: 0.8,
                        passed: false,
                        description: '检测异常'
                    },
                    laplacianVariance: {
                        name: '拉普拉斯方差',
                        value: 0,
                        threshold: config.min_laplacian_variance,
                        passed: false,
                        description: '检测异常'
                    },
                    gradientSharpness: {
                        name: '梯度清晰度',
                        value: 0,
                        threshold: config.min_gradient_sharpness,
                        passed: false,
                        description: '检测异常'
                    },
                    overallQuality: {
                        name: '综合图像质量',
                        value: 0,
                        threshold: threshold,
                        passed: false,
                        description: '检测异常'
                    }
                }
            };
        }
    }
    // ==================== 清晰度检测 ====================
    /**
     * 检测图像清晰度（内部函数）
     *
     * 使用混合算法：
     * 1. 拉普拉斯方差 (Laplacian Variance) - 主要指标（必须通过）
     * 2. Sobel 梯度清晰度 - 辅助指标（必须通过）
     *
     * 判定逻辑：两个指标都通过才认为图像清晰，确保图像质量严格达标
     *
     * @param cv - OpenCV.js 对象
     * @param grayFrame - 灰度图像 Mat 对象
     * @param config - 清晰度检测配置
     * @returns 各指标结果及综合评分
     */
    function checkImageSharpness(cv, grayFrame, config) {
        try {
            // 方法 1：拉普拉斯方差
            const laplacianResult = calculateLaplacianVariance(cv, grayFrame, config.min_laplacian_variance);
            // 方法 2：梯度清晰度
            const gradientResult = calculateGradientSharpness(cv, grayFrame, config.min_gradient_sharpness);
            // 综合评分逻辑：两个指标都通过才认为图像清晰
            // 这确保了被接受的图像质量严格达标，避免某个指标不足被另一个掩盖
            let overallScore = 0;
            if (laplacianResult.passed && gradientResult.passed) {
                // 两个指标都通过：综合评分为高分（取两个分数的加权平均作为参考）
                const laplacianScore = Math.min(1, laplacianResult.value / OPENCV_PARAMS.laplacian_variance_scale);
                const gradientScore = gradientResult.value;
                overallScore = QUALITY_WEIGHTS.laplacian * laplacianScore + QUALITY_WEIGHTS.gradient * gradientScore;
            }
            else {
                // 任意指标不通过：综合评分为低分，但保留原始指标分数供参考
                const laplacianScore = Math.min(1, laplacianResult.value / OPENCV_PARAMS.laplacian_variance_scale);
                const gradientScore = gradientResult.value;
                overallScore = (QUALITY_WEIGHTS.laplacian * laplacianScore + QUALITY_WEIGHTS.gradient * gradientScore) * 0.5;
            }
            return {
                laplacianVariance: laplacianResult,
                gradientSharpness: gradientResult,
                overallScore: Math.min(1, overallScore)
            };
        }
        catch (error) {
            const errorMsg = error instanceof Error ? error.message : String(error);
            console.error('[ImageQuality] Sharpness check error:', errorMsg);
            return {
                laplacianVariance: {
                    name: '拉普拉斯方差',
                    value: 0,
                    threshold: config.min_laplacian_variance,
                    passed: false,
                    description: `检测失败: ${errorMsg}`
                },
                gradientSharpness: {
                    name: '梯度清晰度',
                    value: 0,
                    threshold: config.min_gradient_sharpness,
                    passed: false,
                    description: `检测失败: ${errorMsg}`
                },
                overallScore: 0
            };
        }
    }
    /**
     * 计算拉普拉斯方差
     */
    function calculateLaplacianVariance(cv, grayFrame, minThreshold) {
        let laplacian = null;
        let mean = null;
        let stddev = null;
        try {
            laplacian = new cv.Mat();
            cv.Laplacian(grayFrame, laplacian, cv.CV_64F);
            mean = new cv.Mat();
            stddev = new cv.Mat();
            cv.meanStdDev(laplacian, mean, stddev);
            const variance = stddev.doubleAt(0, 0) ** 2;
            const passed = variance >= minThreshold;
            return {
                name: '拉普拉斯方差',
                value: variance,
                threshold: minThreshold,
                passed,
                description: `拉普拉斯方差 ${variance.toFixed(1)} ${passed ? '✓' : '✗ 需 ≥' + minThreshold}`
            };
        }
        catch (error) {
            const errorMsg = error instanceof Error ? error.message : String(error);
            return {
                name: '拉普拉斯方差',
                value: 0,
                threshold: minThreshold,
                passed: false,
                description: `计算失败: ${errorMsg}`
            };
        }
        finally {
            if (laplacian)
                laplacian.delete();
            if (mean)
                mean.delete();
            if (stddev)
                stddev.delete();
        }
    }
    /**
     * 计算 Sobel 梯度清晰度
     * 使用梯度幅度的标准差而非平均值，更能反映图像的纹理丰富度和清晰度
     */
    function calculateGradientSharpness(cv, grayFrame, minThreshold) {
        let gradX = null;
        let gradY = null;
        let gradMagnitude = null;
        let mean = null;
        let stddev = null;
        try {
            gradX = new cv.Mat();
            gradY = new cv.Mat();
            cv.Sobel(grayFrame, gradX, cv[OPENCV_PARAMS.sobel_type], 1, 0, OPENCV_PARAMS.sobel_kernel_size);
            cv.Sobel(grayFrame, gradY, cv[OPENCV_PARAMS.sobel_type], 0, 1, OPENCV_PARAMS.sobel_kernel_size);
            gradMagnitude = new cv.Mat();
            cv.magnitude(gradX, gradY, gradMagnitude);
            // 使用梯度的标准差作为清晰度指标（比平均值更能反映纹理复杂度）
            mean = new cv.Mat();
            stddev = new cv.Mat();
            cv.meanStdDev(gradMagnitude, mean, stddev);
            // 标准差越大，梯度变化越剧烈，图像纹理越丰富，通常越清晰
            const gradientVariance = stddev.doubleAt(0, 0);
            const sharpnessScore = Math.min(1, gradientVariance / 50); // 标准差的归一化尺度
            const passed = sharpnessScore >= minThreshold;
            return {
                name: '梯度清晰度',
                value: sharpnessScore,
                threshold: minThreshold,
                passed,
                description: `梯度清晰度 ${(sharpnessScore * 100).toFixed(1)}% ${passed ? '✓' : '✗ 需 ≥' + (minThreshold * 100).toFixed(0) + '%'}`
            };
        }
        catch (error) {
            const errorMsg = error instanceof Error ? error.message : String(error);
            return {
                name: '梯度清晰度',
                value: 0,
                threshold: minThreshold,
                passed: false,
                description: `计算失败: ${errorMsg}`
            };
        }
        finally {
            if (gradX)
                gradX.delete();
            if (gradY)
                gradY.delete();
            if (gradMagnitude)
                gradMagnitude.delete();
            if (mean)
                mean.delete();
            if (stddev)
                stddev.delete();
        }
    }

    /**
     * Face Detection Engine - Library Loader
     * Handles loading of Human.js and OpenCV.js
     */
    let cvModule = cvModuleImport__namespace;
    // 如果存在 default 导出（ESM/UMD 互操作），则使用 default
    if (cvModuleImport__namespace && cvModuleImport__namespace.default) {
        cvModule = cvModuleImport__namespace.default;
    }
    let webglAvailableCache = null;
    let opencvInitPromise = null;
    function _isWebGLAvailable() {
        if (webglAvailableCache !== null) {
            return webglAvailableCache;
        }
        try {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('webgl') || canvas.getContext('webgl2');
            webglAvailableCache = !!context;
            return webglAvailableCache;
        }
        catch (e) {
            webglAvailableCache = false;
            return false;
        }
    }
    function detectBrowserEngine(userAgent) {
        const ua = userAgent.toLowerCase();
        // 1. 检测 Gecko (Firefox)
        if (/firefox/i.test(ua) && !/seamonkey/i.test(ua)) {
            return 'gecko';
        }
        // 2. 检测 Chromium/Blink（必须在 WebKit 之前，因为 Chrome 的 user-agent 也包含 WebKit）
        // Chrome-based browsers: Chrome, Chromium, Edge, Brave, Opera, Vivaldi, Whale, Arc, etc.
        if (/chrome|chromium|crios|edge|edgios|edg|brave|opera|vivaldi|whale|arc|yabrowser|samsung|kiwi|ghostery/i.test(ua)) {
            return 'chromium';
        }
        // 3. 检测 WebKit（真正的 Safari 和 iOS 浏览器）
        // 注意：真正的 WebKit 浏览器（Safari）user-agent 不包含 Chrome 标识
        // 包括：Safari、iOS 浏览器、以及那些虽然包含 Chrome 标识但实际是 WebKit 的浏览器（Quark、支付宝、微信等）
        if (/webkit/i.test(ua)) {
            // WebKit 特征明显，包括以下几种情况：
            // - 真正的 Safari（有 Safari 标识）
            // - iOS 浏览器（有 Mobile Safari 标识）
            // - Quark、支付宝、微信等虽然包含 Chrome 标识但是基于 WebKit 的浏览器
            return 'webkit';
        }
        // 4. 其他浏览器 - 保守方案，使用 WASM
        return 'other';
    }
    /**
     * 检测环境信息（用于诊断）
     */
    function _detectEnvironmentInfo() {
        const isMobile = /android|iphone|ipad|ipod|opera mini|iemobile|wpdesktop/i.test(navigator.userAgent.toLowerCase());
        const isAndroid = /android/i.test(navigator.userAgent);
        const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
        // 检测内存
        let memory = { available: 'unknown' };
        if (navigator.deviceMemory) {
            memory = { available: `${navigator.deviceMemory}GB` };
        }
        // 检测连接
        let connection = { type: 'unknown' };
        if (navigator.connection) {
            const conn = navigator.connection;
            connection = {
                type: conn.effectiveType,
                downlink: `${conn.downlink}Mbps`,
                rtt: `${conn.rtt}ms`,
                saveData: conn.saveData
            };
        }
        return {
            isMobile,
            isAndroid,
            isIOS,
            memory,
            connection,
            userAgent: navigator.userAgent,
            platform: navigator.userAgentData?.platform || 'unknown',
            language: navigator.language,
            hardwareConcurrency: navigator.hardwareConcurrency,
            maxTouchPoints: navigator.maxTouchPoints,
            vendor: navigator.vendor
        };
    }
    function _getOptimalBackendForEngine(engine) {
        // 针对不同内核的优化策略
        const backendConfig = {
            chromium: 'webgl', // Chromium 内核：优先 WebGL
            webkit: 'wasm', // WebKit（Safari、iOS）：使用 WASM
            gecko: 'webgl', // Firefox：优先 WebGL
            other: 'wasm' // 未知浏览器：保守使用 WASM
        };
        return backendConfig[engine];
    }
    function _detectOptimalBackend(preferredBackend) {
        // If user explicitly specified a backend, honor it (unless it's 'auto')
        if (preferredBackend && preferredBackend !== 'auto') {
            console.log('[Backend Detection] Using user-specified backend:', {
                backend: preferredBackend,
                userAgent: navigator.userAgent
            });
            return preferredBackend;
        }
        const userAgent = navigator.userAgent.toLowerCase();
        const engine = detectBrowserEngine(userAgent);
        console.log('[Backend Detection] Detected browser engine:', {
            engine,
            userAgent: navigator.userAgent
        });
        // 获取该内核的推荐后端
        let preferredBackendForEngine = _getOptimalBackendForEngine(engine);
        // 对于 Chromium 和 Gecko，检查 WebGL 是否可用
        if (preferredBackendForEngine === 'webgl') {
            const hasWebGL = _isWebGLAvailable();
            console.log('[Backend Detection] WebGL availability check:', {
                engine,
                hasWebGL,
                selectedBackend: hasWebGL ? 'webgl' : 'wasm'
            });
            return hasWebGL ? 'webgl' : 'wasm';
        }
        // 对于 WebKit 和 other，直接使用 WASM
        console.log('[Backend Detection] Using backend for engine:', {
            engine,
            backend: preferredBackendForEngine
        });
        return preferredBackendForEngine;
    }
    /**
     * 预加载 OpenCV.js 以确保全局 cv 对象可用
     * 这是一个异步函数，应该在应用启动时调用
     * @param timeout - Maximum wait time in milliseconds (default: 30000)
     */
    async function preloadOpenCV(timeout = 30000) {
        // 如果已经在初始化中，返回现有的 Promise
        if (opencvInitPromise) {
            console.log('[OpenCV] Already initializing, reusing existing promise');
            await opencvInitPromise;
            return;
        }
        // 复用 loadOpenCV 的初始化逻辑
        opencvInitPromise = _initializeOpenCV(timeout);
        try {
            await opencvInitPromise;
            console.log('[OpenCV] Preload completed successfully');
        }
        catch (error) {
            console.error('[OpenCV] Preload failed:', error);
            // 失败后清除 Promise，允许重试
            opencvInitPromise = null;
            throw error;
        }
    }
    /**
     * Internal helper to initialize OpenCV
     * This is the core initialization logic shared by both preloadOpenCV and loadOpenCV
     */
    async function _initializeOpenCV(timeout) {
        const initStartTime = performance.now();
        console.log('[FaceDetectionEngine] Waiting for OpenCV WASM initialization...');
        // 快速路径：检查是否已经初始化
        if (cvModule.Mat) {
            const initTime = performance.now() - initStartTime;
            console.log(`[FaceDetectionEngine] OpenCV.js already initialized, took ${initTime.toFixed(2)}ms`);
            return true;
        }
        if (typeof globalThis !== 'undefined' && globalThis.cv && globalThis.cv.Mat) {
            const initTime = performance.now() - initStartTime;
            console.log(`[FaceDetectionEngine] OpenCV.js already initialized (from global), took ${initTime.toFixed(2)}ms`);
            cvModule = globalThis.cv;
            return cvModule;
        }
        // 确保 cvModule 在全局可用（OpenCV 会尝试访问它）
        if (typeof globalThis !== 'undefined' && !globalThis.cv) {
            if (cvModule && Object.isExtensible(cvModule)) {
                globalThis.cv = cvModule;
                console.log('[FaceDetectionEngine] cvModule assigned to globalThis.cv');
            }
            else {
                console.log('[FaceDetectionEngine] cvModule is not extensible or globalThis already has cv');
            }
        }
        return new Promise((resolve, reject) => {
            let pollInterval = null;
            const timeoutId = setTimeout(() => {
                console.error('[FaceDetectionEngine] OpenCV.js initialization timeout after ' + timeout + 'ms');
                if (pollInterval) {
                    clearInterval(pollInterval);
                }
                reject(new Error('OpenCV.js initialization timeout'));
            }, timeout);
            let resolved = false;
            const resolveOnce = (source) => {
                if (resolved)
                    return;
                resolved = true;
                clearTimeout(timeoutId);
                if (pollInterval) {
                    clearInterval(pollInterval);
                }
                const initTime = performance.now() - initStartTime;
                console.log(`[FaceDetectionEngine] OpenCV.js initialized (${source}), took ${initTime.toFixed(2)}ms`);
                resolve(true);
            };
            // 尝试设置回调（只有在 cvModule 可扩展时才尝试）
            const canSetCallback = cvModule && Object.isExtensible(cvModule);
            if (canSetCallback) {
                try {
                    const originalCallback = cvModule.onRuntimeInitialized;
                    const newCallback = () => {
                        console.log('[FaceDetectionEngine] onRuntimeInitialized callback triggered');
                        // 调用原始回调（如果存在）
                        if (originalCallback && typeof originalCallback === 'function') {
                            try {
                                originalCallback();
                            }
                            catch (e) {
                                console.warn('[FaceDetectionEngine] Original onRuntimeInitialized callback failed:', e);
                            }
                        }
                        resolveOnce('callback');
                    };
                    cvModule.onRuntimeInitialized = newCallback;
                    console.log('[FaceDetectionEngine] onRuntimeInitialized callback set successfully');
                }
                catch (e) {
                    console.warn('[FaceDetectionEngine] Failed to set onRuntimeInitialized callback, will use polling:', e);
                }
            }
            else {
                console.log('[FaceDetectionEngine] cvModule is not extensible, using polling mode');
            }
            // 启动轮询作为备用方案或主要方案
            pollInterval = setInterval(() => {
                // 优先检查 cvModule 中是否有 Mat
                if (cvModule.Mat) {
                    resolveOnce('cvModule polling');
                    return;
                }
                // 其次检查 globalThis.cv 中是否有 Mat
                if (typeof globalThis !== 'undefined' && globalThis.cv && globalThis.cv.Mat) {
                    cvModule = globalThis.cv;
                    resolveOnce('globalThis.cv polling');
                    return;
                }
            }, 100);
        });
    }
    /**
     * Load OpenCV.js
     * 如果已经通过 preloadOpenCV 在加载中或加载完成，会复用其结果
     * @returns Promise that resolves with cv module
     */
    async function loadOpenCV(timeout = 30000) {
        let cv;
        console.log('[FaceDetectionEngine] Loading OpenCV.js...');
        try {
            // 如果已经在初始化中，复用现有的 Promise
            if (opencvInitPromise) {
                console.log('[FaceDetectionEngine] OpenCV initialization in progress, waiting...');
                try {
                    await opencvInitPromise;
                    cv = getCvSync();
                }
                catch (error) {
                    // 失败后清除 Promise，允许重试
                    opencvInitPromise = null;
                    throw error;
                }
            }
            else {
                cv = getCvSync();
                if (cv && cv.Mat) {
                    console.log('[FaceDetectionEngine] OpenCV.js already initialized');
                    return { cv };
                }
                // 开始新的初始化
                console.log('[FaceDetectionEngine] Starting OpenCV initialization...');
                opencvInitPromise = _initializeOpenCV(timeout);
                try {
                    await opencvInitPromise;
                    cv = getCvSync();
                }
                catch (error) {
                    // 失败后清除 Promise，允许重试
                    opencvInitPromise = null;
                    throw error;
                }
            }
            // 最终验证
            if (!cv || !cv.Mat) {
                console.error('[FaceDetectionEngine] OpenCV module is invalid:', {
                    hasMat: cv && cv.Mat,
                    type: typeof cv,
                    keys: cv ? Object.keys(cv).slice(0, 10) : 'N/A'
                });
                throw new Error('OpenCV.js loaded but module is invalid (no Mat class found)');
            }
            console.log('[FaceDetectionEngine] OpenCV.js loaded successfully');
            return { cv };
        }
        catch (error) {
            console.error('[FaceDetectionEngine] Failed to load OpenCV.js:', error);
            throw error;
        }
    }
    /**
     * Get OpenCV module synchronously (if already loaded)
     * @returns cv module or null
     */
    function getCvSync() {
        // 首先检查全局 cv 对象
        if (typeof globalThis !== 'undefined' && globalThis.cv && globalThis.cv.Mat) {
            return globalThis.cv;
        }
        // 然后检查 cvModule
        if (cvModule.Mat) {
            return cvModule;
        }
        return null;
    }
    /**
     * Create Human.js configuration object
     * 使用 blazeface (人脸检测) 和 facemesh (脸部关键点) 模型
     */
    function _createHumanConfig(backend, modelPath, wasmPath) {
        const config = {
            backend,
            face: {
                enabled: true,
                detector: {
                    enabled: true, // blazeface 人脸检测器
                    rotation: false,
                    return: true
                },
                mesh: {
                    enabled: true, // facemesh 脸部关键点
                },
                // Use the same local FaceRes descriptor for enrollment and
                // verification; inference is forced only after liveness passes.
                description: {
                    enabled: true,
                    modelPath: 'faceres.json',
                    skipFrames: 99,
                    skipTime: 3000
                },
                // Emotion inference is not part of enrollment/liveness and
                // requires an additional model that is not shipped by QA.
                emotion: { enabled: false },
                iris: { enabled: false },
                antispoof: { enabled: false },
                liveness: { enabled: false }
            },
            body: { enabled: false },
            hand: { enabled: false },
            object: { enabled: false },
            gesture: { enabled: true } // 启用手势识别以检测面部表情和头部动作
        };
        if (modelPath) {
            config.modelBasePath = modelPath;
        }
        if (wasmPath) {
            config.wasmPath = wasmPath;
        }
        return config;
    }
    /**
     * Load and verify Human.js models
     */
    async function _loadAndVerifyHuman(human) {
        const modelLoadStartTime = performance.now();
        try {
            await human.load();
        }
        catch (error) {
            const errorMsg = error instanceof Error ? error.message : 'Unknown error';
            const errorStack = error instanceof Error ? error.stack : 'N/A';
            const isCORSError = errorMsg.toLowerCase().includes('cors') ||
                errorMsg.toLowerCase().includes('cross-origin') ||
                errorMsg.toLowerCase().includes('blocked by cors policy');
            const isNetworkError = errorMsg.toLowerCase().includes('network') ||
                errorMsg.toLowerCase().includes('failed to fetch') ||
                errorMsg.toLowerCase().includes('fetch failed');
            const isWASMError = errorMsg.toLowerCase().includes('wasm') ||
                errorMsg.toLowerCase().includes('webassembly');
            console.error('[FaceDetectionEngine] Error during human.load():', {
                errorMsg,
                stack: errorStack,
                backend: human.config?.backend,
                hasModels: !!human.models,
                modelsKeys: human.models ? Object.keys(human.models).length : 0,
                isCORSError,
                isNetworkError,
                isWASMError,
                modelBasePath: human.config?.modelBasePath,
                wasmPath: human.config?.wasmPath,
                userAgent: navigator.userAgent
            });
            throw new Error(`Model loading error (${human.config?.backend} backend): ${errorMsg}`);
        }
        const loadTime = performance.now() - modelLoadStartTime;
        console.log('[FaceDetectionEngine] Human.js loaded successfully', {
            modelLoadTime: `${loadTime.toFixed(2)}ms`,
            version: human.version,
            config: human.config
        });
        // 验证加载后的 Human 实例有必要的方法和属性
        if (typeof human.detect !== 'function') {
            throw new Error('Human.detect method not available after loading');
        }
        if (!human.version) {
            console.warn('[FaceDetectionEngine] Human.js loaded but version is missing');
        }
        // 关键验证：检查模型是否真的加载了
        if (!human.models || Object.keys(human.models).length === 0) {
            console.error('[FaceDetectionEngine] CRITICAL: human.models is empty after loading!');
            throw new Error('No models were loaded - human.models is empty');
        }
        // 打印加载的模型信息
        if (human.models) {
            const loadedModels = Object.entries(human.models).map(([name, model]) => ({
                name,
                loaded: model?.loaded || model?.state === 'loaded',
                type: typeof model,
                hasModel: !!model?.model,
                keys: Object.keys(model).length
            }));
            console.log('[FaceDetectionEngine] All loaded models:', {
                backend: human.config?.backend,
                modelBasePath: human.config?.modelBasePath,
                wasmPath: human.config?.wasmPath,
                totalModels: Object.keys(human.models).length,
                models: loadedModels,
                allModelNames: Object.keys(human.models)
            });
        }
    }
    /**
     * Try to load Human with a specific backend
     * @param config The configuration object
     * @param backend The backend to try
     * @returns Human instance or null if fails
     */
    async function _tryLoadHumanWithBackend(backend, modelPath, wasmPath) {
        const config = _createHumanConfig(backend, modelPath, wasmPath);
        const initStartTime = performance.now();
        let human;
        try {
            human = new Human(config);
        }
        catch (error) {
            const errorMsg = error instanceof Error ? error.message : 'Unknown error during Human instantiation';
            const stack = error instanceof Error ? error.stack : 'N/A';
            console.error(`[FaceDetectionEngine] Failed to create Human instance (${backend}):`, {
                errorMsg,
                stack,
                backend: config.backend,
                userAgent: navigator.userAgent,
                isCORSError: errorMsg.toLowerCase().includes('cors'),
                isNetworkError: errorMsg.toLowerCase().includes('network'),
                isWASMError: errorMsg.toLowerCase().includes('wasm'),
                environmentInfo: _detectEnvironmentInfo()
            });
            return null;
        }
        // 验证 Human 实例
        if (!human) {
            console.error(`[FaceDetectionEngine] Human instance is null (${backend})`);
            return null;
        }
        try {
            console.log(`[FaceDetectionEngine] Starting model loading for ${backend} backend...`);
            await _loadAndVerifyHuman(human);
            const totalTime = performance.now() - initStartTime;
            console.log(`[FaceDetectionEngine] Successfully loaded Human.js with ${backend} backend in ${totalTime.toFixed(2)}ms`);
            return human;
        }
        catch (error) {
            const errorMsg = error instanceof Error ? error.message : 'Unknown error';
            const stack = error instanceof Error ? error.stack : 'N/A';
            console.error(`[FaceDetectionEngine] Failed to load models with ${backend} backend:`, {
                errorMsg,
                stack,
                backend,
                modelPath,
                wasmPath,
                isCORSError: errorMsg.toLowerCase().includes('cors') || errorMsg.toLowerCase().includes('cross-origin'),
                isNetworkError: errorMsg.toLowerCase().includes('network') || errorMsg.toLowerCase().includes('failed to fetch'),
                isWASMError: errorMsg.toLowerCase().includes('wasm'),
                duration: `${(performance.now() - initStartTime).toFixed(2)}ms`,
                environmentInfo: _detectEnvironmentInfo()
            });
            return null;
        }
    }
    /**
     * Load Human.js
     * @param modelPath - Path to model files (optional)
     * @param wasmPath - Path to WASM files (optional)
     * @param preferredBackend - Preferred TensorFlow backend: 'auto' | 'webgl' | 'wasm' (default: 'auto')
     * @returns Promise that resolves with Human instance
     */
    async function loadHuman(modelPath, wasmPath, preferredBackend) {
        const selectedBackend = _detectOptimalBackend(preferredBackend);
        const environmentInfo = _detectEnvironmentInfo();
        console.log('[FaceDetectionEngine] Starting Human.js initialization:', {
            selectedBackend,
            modelBasePath: modelPath || '(using default)',
            wasmPath: wasmPath || '(using default)',
            ...environmentInfo
        });
        // 尝试用主后端加载
        console.log(`[FaceDetectionEngine] Attempting to load Human.js with ${selectedBackend} backend...`);
        const human = await _tryLoadHumanWithBackend(selectedBackend, modelPath, wasmPath);
        if (human) {
            return human;
        }
        console.log(`[FaceDetectionEngine] Human.js loading failed with ${selectedBackend} backend.`);
        // 尝试用备选后端加载（最多一次降级）
        let fallbackBackend;
        if (selectedBackend === 'wasm' && _isWebGLAvailable()) {
            fallbackBackend = 'webgl';
        }
        else if (selectedBackend === 'webgl') {
            fallbackBackend = 'wasm';
        }
        if (fallbackBackend) {
            console.warn(`[FaceDetectionEngine] Primary backend (${selectedBackend}) failed, attempting fallback to ${fallbackBackend}...`);
            const humanFallback = await _tryLoadHumanWithBackend(fallbackBackend, modelPath, wasmPath);
            if (humanFallback) {
                console.log(`[FaceDetectionEngine] Successfully loaded with fallback backend: ${fallbackBackend}`);
                return humanFallback;
            }
            const errorDetails = {
                message: `Human.js loading failed: both ${selectedBackend} and ${fallbackBackend} backends failed`,
                backends: {
                    primary: selectedBackend,
                    fallback: fallbackBackend,
                    both_failed: true
                },
                environment: environmentInfo,
                paths: {
                    modelBasePath: modelPath || '(using default)',
                    wasmPath: wasmPath || '(using default)'
                },
                webglAvailable: _isWebGLAvailable()
            };
            console.error('[FaceDetectionEngine] CRITICAL ERROR:', errorDetails);
            throw new Error(errorDetails.message);
        }
        const errorDetails = {
            message: `Human.js loading failed: ${selectedBackend} backend failed (no fallback available)`,
            backend: selectedBackend,
            environment: environmentInfo,
            paths: {
                modelBasePath: modelPath || '(using default)',
                wasmPath: wasmPath || '(using default)'
            },
            webglAvailable: _isWebGLAvailable()
        };
        console.error('[FaceDetectionEngine] CRITICAL ERROR:', errorDetails);
        throw new Error(errorDetails.message);
    }
    /**
     * Extract OpenCV version from getBuildInformation
     * @returns version string like "4.12.0"
     */
    function getOpenCVVersion() {
        try {
            const cv = getCvSync();
            if (!cv || !cv.getBuildInformation) {
                return 'unknown';
            }
            const buildInfo = cv.getBuildInformation();
            // 查找 "Version control:" 或 "OpenCV" 开头的行
            // 格式: "Version control:               4.12.0"
            const versionMatch = buildInfo.match(/Version\s+control:\s+(\d+\.\d+\.\d+)/i);
            if (versionMatch && versionMatch[1]) {
                return versionMatch[1];
            }
            // 备用方案：查找 "OpenCV X.X.X" 格式
            const opencvMatch = buildInfo.match(/OpenCV\s+(\d+\.\d+\.\d+)/i);
            if (opencvMatch && opencvMatch[1]) {
                return opencvMatch[1];
            }
            return 'unknown';
        }
        catch (error) {
            console.error('[getOpenCVVersion] Failed to get version:', error);
            return 'unknown';
        }
    }

    /**
     * 将绘制图片的canvas转换为mat
     * @param {any} cv OpenCV实例
     * @param {HTMLCanvasElement} canvas canvas元素
     * @param {any} dstMat 目标Mat对象，将canvas数据写入此Mat
     * @returns {any | null} - 返回传入的Mat对象，如果转换失败则返回null
     */
    function drawCanvasToMat(cv, canvas, dstMat) {
        try {
            if (!cv || !canvas || !dstMat) {
                return null;
            }
            // Get canvas context
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                return null;
            }
            // Get ImageData from canvas
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            // Copy ImageData to destination Mat (RGBA format)
            dstMat.data.set(imageData.data);
            return dstMat;
        }
        catch (e) {
            return null;
        }
    }
    /**
     * Convert OpenCV Mat to Base64 JPEG image
     * @param {any} cv - OpenCV instance
     * @param {any} mat - OpenCV Mat object
     * @param {number} quality - JPEG quality (0-1), default 0.9
     * @returns {string | null} Base64 encoded JPEG image data
     */
    function matToBase64Jpeg(cv, mat, quality = 0.9) {
        try {
            if (!cv || !mat || mat.empty()) {
                return null;
            }
            // Create temporary canvas to hold the Mat
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = mat.cols;
            tempCanvas.height = mat.rows;
            // Convert Mat back to canvas using imshow
            cv.imshow(tempCanvas, mat);
            // Convert canvas to Base64 JPEG
            const base64Data = tempCanvas.toDataURL('image/jpeg', quality);
            // Properly clean up canvas
            const ctx = tempCanvas.getContext('2d');
            if (ctx) {
                ctx.clearRect(0, 0, tempCanvas.width, tempCanvas.height);
            }
            tempCanvas.width = 0;
            tempCanvas.height = 0;
            return base64Data;
        }
        catch (e) {
            return null;
        }
    }

    /**
     * 人脸运动检测器 - 基于 MediaPipe Face Mesh 的轻量级运动检测机制
     *
     * 核心原理：
     * 通过分析连续帧间人脸关键点的几何变化，检测由头部姿态调整、表情变化或外部移动引起的形变信号。
     *
     * 具体流程：
     * 1. 关键点获取：提取归一化的人脸关键点坐标（x, y ∈ [0,1]），共468个点
     * 2. 平移不变性处理：以鼻尖关键点（索引为1）为原点进行中心化
     * 3. 帧间位移计算：计算当前帧与前一帧的欧氏距离平均值
     * 4. 运动判定：若位移超过阈值，判定为运动状态
     */
    /**
     * 人脸运动检测结果
     */
    class FaceMovingDetectionResult {
        isMoving;
        details;
        available = false;
        trusted = false;
        constructor(isMoving, details, available = false) {
            this.isMoving = isMoving;
            this.details = details;
            this.available = available;
            this.trusted = available;
        }
        getMessage() {
            if (this.details.frameCount < 2) {
                return '数据不足，无法进行运动检测';
            }
            if (!this.isMoving) {
                return '';
            }
            const confidence = (this.details.movementConfidence * 100).toFixed(0);
            const movement = (this.details.currentMovement * 1000).toFixed(1);
            const frames = this.details.continuousMovingFrames;
            return `检测到人脸运动（强度: ${movement}, 置信度: ${confidence}%, 连续帧数: ${frames}）`;
        }
    }
    const DEFAULT_OPTIONS$1 = {
        frameBufferSize: 30, // 30帧
        movementThreshold: 0.015, // 运动阈值：超过此值判定为运动
        minContinuousFrames: 1, // 至少连续1帧运动才认为是有效运动
        nosePointIndex: 1, // MediaPipe Face Mesh 中鼻尖的索引
    };
    /**
     * 人脸运动检测器
     *
     * 基于 MediaPipe Face Mesh 的468个关键点，通过分析帧间几何变化检测运动
     */
    class FaceMovingDetector {
        config;
        frameBuffer = [];
        movementHistory = [];
        continuousMovingCount = 0;
        emitDebug = () => { }; // 默认空实现（不emit）  
        constructor(options) {
            this.config = { ...DEFAULT_OPTIONS$1, ...options };
        }
        /**
        * 设置 emitDebug 方法（依赖注入）
        * @param emitDebugFn - 来自 FaceDetectionEngine 的 emitDebug 方法
        */
        setEmitDebug(emitDebugFn) {
            this.emitDebug = emitDebugFn;
        }
        /**
         * 添加一帧的人脸检测结果
         * @param faceResult - 单帧的人脸检测结果
         * @param timestamp - 高精度时间戳（建议使用 performance.now()，单位毫秒）
         */
        addFrame(faceResult, timestamp = Date.now()) {
            if (!faceResult.meshRaw || faceResult.meshRaw.length === 0)
                return;
            this.frameBuffer.push({ result: faceResult, timestamp });
            // 保持缓冲区大小
            if (this.frameBuffer.length > this.config.frameBufferSize) {
                this.frameBuffer.shift();
                if (this.movementHistory.length > this.config.frameBufferSize) {
                    this.movementHistory.shift();
                }
            }
        }
        /**
         * 执行人脸运动检测
         * @returns 检测结果
         */
        detect() {
            const details = {
                frameCount: this.frameBuffer.length,
                currentMovement: 0,
                averageMovement: 0,
                movementStdDev: 0,
                maxMovement: 0,
                minMovement: 0,
                isMoving: false,
                movementConfidence: 0,
                continuousMovingFrames: 0,
                movementDuration: 0,
                lastCentroidShift: 0,
                centroidShiftRate: 0
            };
            // 帧数不足，无法检测
            if (this.frameBuffer.length < 2) {
                return new FaceMovingDetectionResult(false, details);
            }
            // ============ 计算当前帧的运动强度 ============
            const currentMovement = this.frameBuffer.length >= 2
                ? this.calculateMovement(this.frameBuffer[this.frameBuffer.length - 2].result, this.frameBuffer[this.frameBuffer.length - 1].result)
                : 0;
            details.currentMovement = currentMovement;
            this.movementHistory.push(currentMovement);
            // ============ 计算运动历史统计 ============
            if (this.movementHistory.length > 0) {
                details.averageMovement = this.calculateMean(this.movementHistory);
                details.movementStdDev = this.calculateStdDev(this.movementHistory);
                details.maxMovement = Math.max(...this.movementHistory);
                details.minMovement = Math.min(...this.movementHistory);
            }
            // ============ 运动状态判定 ============
            const isCurrentlyMoving = currentMovement > this.config.movementThreshold;
            if (isCurrentlyMoving) {
                this.continuousMovingCount++;
            }
            else {
                this.continuousMovingCount = 0;
            }
            // 需要连续运动至少 minContinuousFrames 帧才认为是有效运动
            const hasValidMotion = this.continuousMovingCount >= this.config.minContinuousFrames;
            details.isMoving = hasValidMotion;
            details.continuousMovingFrames = this.continuousMovingCount;
            details.movementDuration = this.calculateMovementDuration();
            // ============ 运动置信度计算 ============
            // 基于当前运动强度与阈值的比例
            details.movementConfidence = Math.min(1, currentMovement / this.config.movementThreshold);
            // ============ 中心化坐标偏移 ============
            const lastFrame = this.frameBuffer[this.frameBuffer.length - 1];
            details.lastCentroidShift = this.calculateCentroidShift(lastFrame.result);
            // 计算中心化坐标的变化速率（基于实际时间）
            details.centroidShiftRate = this.calculateCentroidShiftRate();
            return new FaceMovingDetectionResult(details.isMoving, details, true);
        }
        /**
         * 计算两帧之间的运动强度
         *
         * 算法步骤：
         * 1. 对两帧的关键点进行中心化（以鼻尖为原点）
         * 2. 计算对应关键点之间的欧氏距离
         * 3. 取所有距离的平均值作为运动强度
         *
         * @param prevFrame - 前一帧的人脸检测结果
         * @param currFrame - 当前帧的人脸检测结果
         * @returns 运动强度 (0-1)
         */
        calculateMovement(prevFrame, currFrame) {
            const prevMesh = prevFrame.meshRaw;
            const currMesh = currFrame.meshRaw;
            if (!prevMesh || !currMesh || prevMesh.length === 0 || currMesh.length === 0) {
                return 0;
            }
            // ============ 第一步：中心化处理 ============
            const prevCentralized = this.centralizeMesh(prevMesh);
            const currCentralized = this.centralizeMesh(currMesh);
            if (prevCentralized.length === 0 || currCentralized.length === 0) {
                return 0;
            }
            // ============ 第二步：计算帧间位移 ============
            let totalDisplacement = 0;
            let validPointCount = 0;
            for (let i = 0; i < Math.min(prevCentralized.length, currCentralized.length); i++) {
                const prev = prevCentralized[i];
                const curr = currCentralized[i];
                if (!prev || !curr)
                    continue;
                // 计算欧氏距离
                const dx = curr[0] - prev[0];
                const dy = curr[1] - prev[1];
                const distance = Math.sqrt(dx * dx + dy * dy);
                totalDisplacement += distance;
                validPointCount++;
            }
            // ============ 第三步：归一化运动强度 ============
            if (validPointCount === 0) {
                return 0;
            }
            const averageDisplacement = totalDisplacement / validPointCount;
            // 将位移归一化到 [0, 1] 范围
            // 假设最大合理位移为 0.1（相对于图像尺寸）
            // 超过此值仍记为 1.0
            const normalizedMovement = Math.min(1, averageDisplacement / 0.1);
            return normalizedMovement;
        }
        /**
         * 对关键点网格进行中心化处理
         *
         * 为消除人脸整体平移的干扰，以鼻尖（索引为 nosePointIndex）为原点
         * 将所有关键点的坐标转换为相对坐标：
         * p' = p - p_nose
         *
         * @param mesh - 原始关键点坐标数组
         * @returns 中心化后的关键点坐标数组
         */
        centralizeMesh(mesh) {
            if (mesh.length <= this.config.nosePointIndex) {
                return [];
            }
            // 获取鼻尖坐标
            const nosePt = mesh[this.config.nosePointIndex];
            if (!nosePt) {
                return [];
            }
            const noseX = nosePt[0];
            const noseY = nosePt[1];
            // 对每个点进行中心化
            const centralized = [];
            for (const point of mesh) {
                if (!point) {
                    centralized.push([0, 0]);
                    continue;
                }
                const x = point[0] - noseX;
                const y = point[1] - noseY;
                centralized.push([x, y]);
            }
            return centralized;
        }
        /**
         * 计算中心化坐标相对于鼻尖的偏移量
         * 用于衡量关键点分布的整体位置变化
         *
         * @param frame - 人脸检测结果
         * @returns 中心化坐标的偏移量
         */
        calculateCentroidShift(frame) {
            const mesh = frame.meshRaw;
            if (!mesh || mesh.length === 0) {
                return 0;
            }
            const centralized = this.centralizeMesh(mesh);
            if (centralized.length === 0) {
                return 0;
            }
            // 计算所有中心化坐标到原点的距离平均值
            let totalDistance = 0;
            let validCount = 0;
            for (const point of centralized) {
                if (!point)
                    continue;
                const distance = Math.sqrt(point[0] * point[0] + point[1] * point[1]);
                totalDistance += distance;
                validCount++;
            }
            if (validCount === 0) {
                return 0;
            }
            return totalDistance / validCount;
        }
        /**
         * 计算基于真实时间的运动持续时长（秒）
         */
        calculateMovementDuration() {
            if (this.frameBuffer.length < 2)
                return 0;
            const firstTimestamp = this.frameBuffer[0].timestamp;
            const lastTimestamp = this.frameBuffer[this.frameBuffer.length - 1].timestamp;
            return (lastTimestamp - firstTimestamp) / 1000; // 转为秒
        }
        /**
         * 计算中心化坐标的变化速率（单位：每秒）
         */
        calculateCentroidShiftRate() {
            if (this.frameBuffer.length < 2)
                return 0;
            const firstShift = this.calculateCentroidShift(this.frameBuffer[0].result);
            const lastShift = this.calculateCentroidShift(this.frameBuffer[this.frameBuffer.length - 1].result);
            const shiftDistance = Math.abs(lastShift - firstShift);
            const timeSec = (this.frameBuffer[this.frameBuffer.length - 1].timestamp - this.frameBuffer[0].timestamp) / 1000;
            return timeSec > 0 ? shiftDistance / timeSec : 0;
        }
        /**
         * 计算数组的平均值
         */
        calculateMean(values) {
            if (values.length === 0)
                return 0;
            return values.reduce((a, b) => a + b) / values.length;
        }
        /**
         * 计算数组的标准差
         */
        calculateStdDev(values) {
            if (values.length === 0)
                return 0;
            const mean = this.calculateMean(values);
            const squaredDiffs = values.map(v => (v - mean) ** 2);
            const variance = squaredDiffs.reduce((a, b) => a + b) / values.length;
            return Math.sqrt(variance);
        }
        /**
         * 重置检测器
         */
        reset() {
            this.frameBuffer = [];
            this.movementHistory = [];
            this.continuousMovingCount = 0;
        }
        /**
         * 获取当前缓冲区中的帧数
         */
        getFrameCount() {
            return this.frameBuffer.length;
        }
        /**
         * 获取运动历史数据
         */
        getMovementHistory() {
            return [...this.movementHistory];
        }
        /**
         * 获取连续运动帧数
         */
        getContinuousMovingFrames() {
            return this.continuousMovingCount;
        }
    }

    /**
     * 照片攻击检测器
     *
     * 关键点运动透视一致性检验
     * - 比较鼻尖、脸颊、耳朵等在多帧中的 2D 位移比例
     * - 真实人脸因透视效应，近处点移动幅度 > 远处点
     * - 照片上所有点按同一仿射变换移动 → 运动向量高度一致
     */
    /**
     * 照片攻击检测结果
     */
    class PhotoAttackDetectionResult {
        isPhoto;
        details;
        available = false;
        trusted = false;
        constructor(isPhoto, details, available = false, trusted = false) {
            this.isPhoto = isPhoto;
            this.details = details;
            this.available = available;
            this.trusted = trusted;
        }
        getMessage() {
            if (this.details.frameCount < 3) {
                return '数据不足，无法进行照片检测';
            }
            if (!this.isPhoto)
                return '';
            const confidence = (this.details.photoConfidence * 100).toFixed(0);
            const reasons = [];
            if (this.details.perspectiveScore > 0.5) {
                const motionVar = this.details.motionDisplacementVariance.toFixed(3);
                const consistency = (this.details.motionDirectionConsistency * 100).toFixed(0);
                reasons.push(`运动一致性过高(${consistency}%)，位移方差(${motionVar})`);
            }
            const reasonStr = reasons.length > 0 ? `（${reasons.join('、')}）` : '';
            return `检测到照片攻击${reasonStr}，置信度 ${confidence}%`;
        }
    }
    const DEFAULT_OPTIONS = {
        frameBufferSize: 15, // 15帧 (0.5秒@30fps)
        requiredFrameCount: 15, // 可信赖所需的最小帧数
        motionVarianceThreshold: 0.005, // 运动方差阈值：真实人脸 > 0.02，照片 < 0.01
        perspectiveRatioThreshold: 1.05, // 透视比率阈值：真实人脸 > 1, 照片 0.9 ~ 1.0
        motionConsistencyThreshold: 0.8, // 运动一致性阈值：真实人脸 < 0.5，照片 > 0.8
    };
    /**
     * 照片攻击检测器
     *
     * 运动透视一致性检验（纯 2D 几何分析）
     */
    class PhotoAttackDetector {
        config;
        frameBuffer = [];
        frameCount = 0;
        emitDebug = () => { }; // 默认空实现（不emit）  
        constructor(options) {
            this.config = { ...DEFAULT_OPTIONS, ...options };
        }
        /**
        * 设置 emitDebug 方法（依赖注入）
        * @param emitDebugFn - 来自 FaceDetectionEngine 的 emitDebug 方法
        */
        setEmitDebug(emitDebugFn) {
            this.emitDebug = emitDebugFn;
        }
        /**
         * 添加一帧的人脸检测结果
         * @param faceResult - 单帧的人脸检测结果
         */
        addFrame(faceResult) {
            if (!faceResult.meshRaw || faceResult.meshRaw.length === 0)
                return;
            this.frameCount++;
            this.frameBuffer.push(faceResult);
            // 保持缓冲区大小
            if (this.frameBuffer.length > this.config.frameBufferSize) {
                this.frameBuffer.shift();
            }
        }
        /**
         * 执行照片攻击检测
         * @returns 检测结果
         */
        detect() {
            const details = {
                frameCount: this.frameCount,
                motionDisplacementVariance: 0,
                perspectiveRatio: 0,
                motionDirectionConsistency: 0,
                affineTransformPatternMatch: 0,
                perspectiveScore: 0,
                isPhoto: false,
                photoConfidence: 0,
            };
            // 帧数不足，无法检测
            if (this.frameBuffer.length < 3) {
                return new PhotoAttackDetectionResult(false, details);
            }
            // ============ 运动透视一致性检验 ============
            const perspectiveAnalysis = this.analyzePerspectiveConsistency();
            details.motionDisplacementVariance = perspectiveAnalysis.motionDisplacementVariance;
            details.perspectiveRatio = perspectiveAnalysis.perspectiveRatio;
            details.motionDirectionConsistency = perspectiveAnalysis.motionDirectionConsistency;
            details.affineTransformPatternMatch = perspectiveAnalysis.affineTransformPatternMatch;
            details.perspectiveScore = perspectiveAnalysis.score;
            details.isPhoto = perspectiveAnalysis.score > 0.5;
            details.photoConfidence = perspectiveAnalysis.score;
            return new PhotoAttackDetectionResult(details.isPhoto, details, true, this.frameCount >= this.config.requiredFrameCount);
        }
        /**
         * 运动透视一致性检验
         *
         * 原理：
         * - 真实人脸运动：由于透视效应，近处点移动幅度大，远处点移动幅度小
         * - 照片攻击：所有点按照同一仿射变换移动，各点位移比例完全相同
         * - 通过分析多帧中各关键点的位移向量，可以判断是否存在这种一致性模式
         */
        analyzePerspectiveConsistency() {
            // 需要至少 3 帧来计算运动
            if (this.frameBuffer.length < 3) {
                return {
                    motionDisplacementVariance: 0,
                    perspectiveRatio: 0,
                    motionDirectionConsistency: 0,
                    affineTransformPatternMatch: 0,
                    score: 0
                };
            }
            // 选择关键特征点进行分析
            // 鼻子：近处点
            // 脸颊：中距离点
            // 耳朵：远处点
            const keyPointIndices = this.selectKeyPointIndices();
            // 计算各关键点在多帧中的位移向量
            const displacements = this.computeDisplacements(keyPointIndices);
            // 1. 计算各关键点位移的标准差
            // 真实人脸：各点位移差异大 -> 高方差
            // 照片：各点位移一致 -> 低方差
            const motionDisplacementVariance = this.calculateMotionVariance(displacements);
            // 2. 计算透视比率（近处点位移 / 远处点位移）
            // 真实人脸：比率 > 1（近处点移动幅度大）
            // 照片：比率 ≈ 1（所有点移动幅度相同）
            const perspectiveRatio = this.calculatePerspectiveRatio(displacements, keyPointIndices);
            // 3. 计算运动向量的方向一致性
            // 照片：所有点的运动向量方向高度一致
            // 真实人脸：各点运动方向差异大
            const motionDirectionConsistency = this.calculateDirectionConsistency(displacements);
            // 4. 计算仿射变换模式匹配度
            // 尝试用单一仿射变换拟合所有点的位移
            // 照片特征：拟合度高（高度一致的仿射变换）
            // 真实人脸：拟合度低（各点运动不符合单一变换）
            const affineTransformPatternMatch = this.calculateAffineTransformMatch(displacements);
            // 综合计算置信度
            // 各个指标合并：
            // - 位移方差越小（接近0）-> 照片特征越明显 -> score高
            // - 透视比率越接近1 -> 照片特征 -> score高
            // - 方向一致性越高 -> 照片特征 -> score高
            // - 仿射变换匹配度越高 -> 照片特征 -> score高
            const variance_indicator = Math.max(0, 1 - (motionDisplacementVariance / this.config.motionVarianceThreshold));
            // 修改透视比率计算逻辑以支持阈值大于等于1的情况
            let ratio_indicator = 0;
            // 增强perspectiveRatio的灵敏度，当小于1时给予更高权重
            if (perspectiveRatio < 1) {
                // perspectiveRatio < 1: 近处点移动 < 远处点，明显照片特征 → 非常高分
                // 使用更强的非线性放大，让小于1的值得到显著更高的分数
                ratio_indicator = 0.95; // 表示非常像照片
                // const deviation = 1 - perspectiveRatio
                // ratio_indicator = Math.min(1, deviation * 100) // 从10提高到100，增强灵敏度
            }
            else if (this.config.perspectiveRatioThreshold === 1) {
                // 阈值等于1且perspectiveRatio >= 1的情况
                // 近处点移动 >= 远处点，符合透视效应 → 低分
                ratio_indicator = 0;
            }
            else if (this.config.perspectiveRatioThreshold < 1) {
                // 阈值小于1时的逻辑
                const denominator = 1 - this.config.perspectiveRatioThreshold;
                ratio_indicator = Math.max(0, 1 - Math.abs(perspectiveRatio - 1) / denominator);
            }
            else {
                // 阈值大于1时，真实人脸应有更高比率
                // 如果透视比率大于阈值，则更可能是真实人脸，返回低分（非照片）
                // 如果透视比率小于等于阈值，则可能是照片，返回高分
                if (perspectiveRatio < this.config.perspectiveRatioThreshold) {
                    // 透视比率小于阈值，更像照片
                    ratio_indicator = Math.max(0, 1 - (this.config.perspectiveRatioThreshold - perspectiveRatio) / this.config.perspectiveRatioThreshold);
                }
                else {
                    // 透视比率大于阈值，更像真实人脸
                    ratio_indicator = 0; // 真实人脸，返回低分
                }
            }
            const consistency_indicator = Math.min(1, motionDirectionConsistency / this.config.motionConsistencyThreshold);
            const affine_indicator = affineTransformPatternMatch;
            // 改进的计分算法：
            // - 透视比率非常接近1（或小于1），表明是照片
            if (ratio_indicator > 0.9) {
                return {
                    motionDisplacementVariance,
                    perspectiveRatio,
                    motionDirectionConsistency,
                    affineTransformPatternMatch,
                    score: ratio_indicator
                };
            }
            // 没有指标明显表明是照片，使用加权平均
            // 给予ratio_indicator更高的权重（2倍），其他指标保持原有权重
            const score = Math.min(1, (variance_indicator + ratio_indicator * 2 + consistency_indicator + affine_indicator) / 5);
            return {
                motionDisplacementVariance,
                perspectiveRatio,
                motionDirectionConsistency,
                affineTransformPatternMatch,
                score
            };
        }
        /**
         * 选择用于分析的关键点索引
         * 选择鼻子（近处）、脸颊（中距离）、耳朵（远处）作为代表
         */
        selectKeyPointIndices() {
            // MediaPipe 468个关键点的已知索引
            // 这些是常用的特征点索引
            return {
                near: [1, 4, 6, 195], // 鼻尖及周围（近处）
                mid: [127, 356], // 脸颊（中距离）
                far: [162, 389] // 耳朵（远处）
            };
        }
        /**
         * 计算各关键点在多帧中的位移向量
         */
        computeDisplacements(keyPointIndices) {
            const result = { near: [], mid: [], far: [] };
            // 遍历帧对，计算位移
            for (let i = 1; i < this.frameBuffer.length; i++) {
                const prevMesh = this.frameBuffer[i - 1].meshRaw;
                const currMesh = this.frameBuffer[i].meshRaw;
                if (!prevMesh || !currMesh)
                    continue;
                // 计算近处点的位移
                for (const idx of keyPointIndices.near) {
                    if (idx < prevMesh.length && idx < currMesh.length) {
                        const displacement = {
                            x: currMesh[idx][0] - prevMesh[idx][0],
                            y: currMesh[idx][1] - prevMesh[idx][1],
                            magnitude: 0
                        };
                        displacement.magnitude = Math.sqrt(displacement.x ** 2 + displacement.y ** 2);
                        result.near.push(displacement);
                    }
                }
                // 计算中距离点的位移
                for (const idx of keyPointIndices.mid) {
                    if (idx < prevMesh.length && idx < currMesh.length) {
                        const displacement = {
                            x: currMesh[idx][0] - prevMesh[idx][0],
                            y: currMesh[idx][1] - prevMesh[idx][1],
                            magnitude: 0
                        };
                        displacement.magnitude = Math.sqrt(displacement.x ** 2 + displacement.y ** 2);
                        result.mid.push(displacement);
                    }
                }
                // 计算远处点的位移
                for (const idx of keyPointIndices.far) {
                    if (idx < prevMesh.length && idx < currMesh.length) {
                        const displacement = {
                            x: currMesh[idx][0] - prevMesh[idx][0],
                            y: currMesh[idx][1] - prevMesh[idx][1],
                            magnitude: 0
                        };
                        displacement.magnitude = Math.sqrt(displacement.x ** 2 + displacement.y ** 2);
                        result.far.push(displacement);
                    }
                }
            }
            return result;
        }
        /**
         * 计算运动位移的方差
         * 低方差 = 照片（所有点一致运动）
         * 高方差 = 真实人脸（各点运动差异大）
         */
        calculateMotionVariance(displacements) {
            const allMagnitudes = [];
            allMagnitudes.push(...displacements.near.map(d => d.magnitude));
            allMagnitudes.push(...displacements.mid.map(d => d.magnitude));
            allMagnitudes.push(...displacements.far.map(d => d.magnitude));
            if (allMagnitudes.length === 0)
                return 0;
            return this.calculateVariance(allMagnitudes);
        }
        /**
         * 计算透视比率（近处点位移 / 远处点位移）
         * 真实人脸：比率 > 1
         * 照片：比率 ≈ 1
         */
        calculatePerspectiveRatio(displacements, keyPointIndices) {
            const nearMagnitudes = displacements.near.map(d => d.magnitude);
            const farMagnitudes = displacements.far.map(d => d.magnitude);
            if (nearMagnitudes.length === 0 || farMagnitudes.length === 0)
                return 1;
            const nearAvg = nearMagnitudes.reduce((a, b) => a + b) / nearMagnitudes.length;
            const farAvg = farMagnitudes.reduce((a, b) => a + b) / farMagnitudes.length;
            if (farAvg === 0)
                return 1;
            return nearAvg / farAvg;
        }
        /**
         * 计算运动向量的方向一致性
         * 照片：所有点的运动方向高度一致 -> 高分
         * 真实人脸：各点运动方向差异大 -> 低分
         */
        calculateDirectionConsistency(displacements) {
            const allDisplacements = [];
            allDisplacements.push(...displacements.near);
            allDisplacements.push(...displacements.mid);
            allDisplacements.push(...displacements.far);
            if (allDisplacements.length < 2)
                return 0;
            // 计算平均方向
            const avgX = allDisplacements.reduce((sum, d) => sum + d.x, 0) / allDisplacements.length;
            const avgY = allDisplacements.reduce((sum, d) => sum + d.y, 0) / allDisplacements.length;
            const avgMagnitude = Math.sqrt(avgX ** 2 + avgY ** 2);
            if (avgMagnitude === 0)
                return 0;
            // 规范化平均方向
            const avgDirX = avgX / avgMagnitude;
            const avgDirY = avgY / avgMagnitude;
            // 计算每个位移向量与平均方向的夹角余弦值
            let totalConsistency = 0;
            for (const d of allDisplacements) {
                const magnitude = Math.sqrt(d.x ** 2 + d.y ** 2);
                if (magnitude === 0)
                    continue;
                const dirX = d.x / magnitude;
                const dirY = d.y / magnitude;
                // 夹角的余弦值（-1到1，1表示完全一致）
                const cosAngle = dirX * avgDirX + dirY * avgDirY;
                // 转换为 0-1 范围（0表示垂直，1表示平行）
                totalConsistency += (cosAngle + 1) / 2;
            }
            return totalConsistency / allDisplacements.length;
        }
        /**
         * 计算仿射变换模式匹配度
         * 使用更精确的仿射变换模型拟合所有点的位移
         * 拟合度高 = 照片特征
         */
        calculateAffineTransformMatch(displacements) {
            const allDisplacements = [];
            allDisplacements.push(...displacements.near);
            allDisplacements.push(...displacements.mid);
            allDisplacements.push(...displacements.far);
            if (allDisplacements.length < 3)
                return 0;
            // 计算各点位移与整体平均位移的相似度
            // 照片攻击中，各点位移应高度相似（同一仿射变换）
            const avgX = allDisplacements.reduce((sum, d) => sum + d.x, 0) / allDisplacements.length;
            const avgY = allDisplacements.reduce((sum, d) => sum + d.y, 0) / allDisplacements.length;
            const avgMagnitude = Math.sqrt(avgX ** 2 + avgY ** 2);
            if (avgMagnitude === 0)
                return 0;
            // 计算每个位移向量与平均位移向量的相似度
            let totalSimilarity = 0;
            for (const d of allDisplacements) {
                const displacementMagnitude = Math.sqrt(d.x ** 2 + d.y ** 2);
                if (displacementMagnitude === 0) {
                    // 没有位移的点视为与平均位移一致
                    totalSimilarity += 1;
                    continue;
                }
                // 计算方向相似度（点积）
                const directionSimilarity = (d.x * avgX + d.y * avgY) / (displacementMagnitude * avgMagnitude);
                // 计算幅度相似度
                const magnitudeSimilarity = Math.min(displacementMagnitude, avgMagnitude) / Math.max(displacementMagnitude, avgMagnitude);
                // 综合方向和幅度相似度
                const similarity = (directionSimilarity + 1) / 2 * magnitudeSimilarity;
                totalSimilarity += similarity;
            }
            // 返回平均相似度
            return totalSimilarity / allDisplacements.length;
        }
        /**
         * 计算方差（用于数组）
         */
        calculateVariance(values) {
            if (values.length === 0)
                return 0;
            const mean = values.reduce((a, b) => a + b) / values.length;
            const squaredDiffs = values.map(v => (v - mean) ** 2);
            return squaredDiffs.reduce((a, b) => a + b) / values.length;
        }
        /**
         * 重置检测器
         */
        reset() {
            this.frameBuffer = [];
        }
    }

    /**
     * Internal detection state interface
     */
    class DetectionState {
        period = exports.DetectionPeriod.DETECT;
        startTime = performance.now();
        collectCount = 0;
        bestQualityScore = 0;
        bestFrameImage = null;
        bestFaceImage = null;
        completedActions = new Set();
        currentAction = null;
        actionVerifyTimeout = null;
        lastFrontalScore = 1;
        faceMovingDetector = null;
        photoAttackDetector = null;
        liveness = false;
        constructor(options) {
            Object.assign(this, options);
        }
        reset() {
            this.clearActionVerifyTimeout();
            const savedFaceMovingDetector = this.faceMovingDetector;
            const savedPhotoAttackDetector = this.photoAttackDetector;
            savedFaceMovingDetector?.reset();
            savedPhotoAttackDetector?.reset();
            Object.assign(this, new DetectionState({}));
            this.faceMovingDetector = savedFaceMovingDetector;
            this.photoAttackDetector = savedPhotoAttackDetector;
        }
        // 默认方法
        needFrontalFace() {
            return this.period !== exports.DetectionPeriod.VERIFY;
        }
        // 是否准备好进行动作验证
        isReadyToVerify(minCollectCount) {
            if (this.period === exports.DetectionPeriod.COLLECT
                && this.liveness
                && this.collectCount >= minCollectCount) {
                return true;
            }
            return false;
        }
        onActionStarted(nextAction, timeoutMills, timeoutCallback) {
            if (nextAction === null) {
                return;
            }
            this.currentAction = nextAction;
            this.clearActionVerifyTimeout();
            this.actionVerifyTimeout = setTimeout(timeoutCallback, timeoutMills);
        }
        onActionCompleted() {
            if (this.currentAction === null) {
                return;
            }
            this.clearActionVerifyTimeout();
            this.completedActions.add(this.currentAction);
            this.currentAction = null;
        }
        /**
         * Clear action verify timeout
         */
        clearActionVerifyTimeout() {
            if (this.actionVerifyTimeout !== null) {
                clearTimeout(this.actionVerifyTimeout);
                this.actionVerifyTimeout = null;
            }
        }
    }
    function createDetectionState(engine) {
        const detectionState = new DetectionState({});
        detectionState.faceMovingDetector = new FaceMovingDetector();
        detectionState.faceMovingDetector.setEmitDebug(engine.emitDebug.bind(engine));
        detectionState.photoAttackDetector = new PhotoAttackDetector({
            requiredFrameCount: engine.options.photo_attack_passed_frame_count || 15
        });
        detectionState.photoAttackDetector.setEmitDebug(engine.emitDebug.bind(engine));
        return detectionState;
    }

    /**
     * Face Detection Engine - Core Detection Engine
     * Framework-agnostic face liveness detection engine
     */
    /**
     * Framework-agnostic face liveness detection engine
     * Provides core detection logic without UI dependencies
     */
    class FaceDetectionEngine extends SimpleEventEmitter {
        options;
        // OpenCV instance
        cv = null;
        human = null;
        engineState = exports.EngineState.IDLE;
        videoFPS = 30;
        // Debug log throttling
        lastDebugLogTime = new Map();
        debugLogLevelPriority = { info: 0, warn: 1, error: 2 };
        // 视频及保存当前帧图片的Canvas元素
        videoElement = null;
        stream = null;
        frameCanvasElement = null;
        frameCanvasContext = null;
        animationFrameId = null;
        actualVideoWidth = 0;
        actualVideoHeight = 0;
        // Pre-allocated Mat objects for frame capture (reused to avoid frequent allocation)
        // Using getImageData() approach allows both BGR and Gray Mat to be preallocated
        preallocatedBgrFrame = null;
        preallocatedGrayFrame = null;
        // 竞态条件控制：防止detect()并发执行
        isDetectingFrameActive = false;
        // Frame-based detection scheduling
        frameIndex = 0;
        // Frame Mat objects created per-frame, cleaned up immediately after use
        detectionState;
        /**
         * Constructor
         * @param config - Configuration object
         */
        constructor(options) {
            super();
            this.options = mergeOptions(options);
            this.detectionState = createDetectionState(this);
        }
        /**
         * 提取错误信息的辅助方法 - 处理各种错误类型
         * @param error - 任意类型的错误对象
         * @returns 包含错误消息和堆栈的对象
         */
        extractErrorInfo(error) {
            // 处理 Error 实例
            if (error instanceof Error) {
                let causeStr;
                if (error.cause) {
                    causeStr = error.cause instanceof Error ? error.cause.message : String(error.cause);
                }
                return {
                    message: error.message || 'Unknown error',
                    stack: error.stack || this.getStackTrace(),
                    name: error.name,
                    cause: causeStr
                };
            }
            // 处理其他对象类型
            if (typeof error === 'object' && error !== null) {
                let causeStr;
                if ('cause' in error) {
                    const cause = error.cause;
                    causeStr = cause instanceof Error ? cause.message : String(cause);
                }
                return {
                    message: error.message || JSON.stringify(error),
                    stack: error.stack || this.getStackTrace(),
                    name: error.name,
                    cause: causeStr
                };
            }
            // 处理基本类型（string, number 等）
            return {
                message: String(error),
                stack: this.getStackTrace()
            };
        }
        /**
         * 获取当前调用栈信息
         */
        getStackTrace() {
            try {
                // 创建一个Error对象来获取堆栈
                const err = new Error();
                if (err.stack) {
                    // 移除前两行（Error 和 getStackTrace 本身）
                    const lines = err.stack.split('\n');
                    return lines.slice(2).join('\n') || 'Stack trace unavailable';
                }
                return 'Stack trace unavailable';
            }
            catch {
                return 'Stack trace unavailable';
            }
        }
        updateOptions(options) {
            // 如果正在检测，先停止检测
            const wasDetecting = this.engineState === exports.EngineState.DETECTING;
            if (wasDetecting) {
                this.stopDetection(false);
            }
            this.options = mergeOptions(options);
            this.detectionState = createDetectionState(this);
            this.emitDebug('config', 'Engine options updated', { wasDetecting }, 'info');
        }
        getEngineState() {
            return this.engineState;
        }
        // ==================== State Management Methods ====================
        /**
         * Atomically transition engine state with validation
         * Ensures state transitions follow the valid state machine
         * @param newState - Target engine state
         * @param context - Debug context for logging
         * @returns true if transition succeeded, false otherwise
         */
        transitionEngineState(newState, context) {
            const oldState = this.engineState;
            // Validate transition
            const isValidTransition = this.isValidStateTransition(oldState, newState);
            if (!isValidTransition) {
                this.emitDebug('state-management', 'Invalid state transition blocked', {
                    from: oldState,
                    to: newState,
                    context: context || 'unknown'
                }, 'warn');
                return false;
            }
            this.engineState = newState;
            this.emitDebug('state-management', 'State transitioned', {
                from: oldState,
                to: newState,
                context: context || 'unknown'
            }, 'info');
            return true;
        }
        /**
         * Check if state transition is valid according to state machine rules
         * Valid transitions:
         * - IDLE -> INITIALIZING, INITIALIZING -> READY, READY -> DETECTING, DETECTING -> READY
         * - Any -> IDLE (error recovery)
         */
        isValidStateTransition(from, to) {
            // Same state is not a transition
            if (from === to)
                return true;
            // Allow recovery to IDLE from any state
            if (to === exports.EngineState.IDLE)
                return true;
            // Valid forward transitions
            const validTransitions = {
                [exports.EngineState.IDLE]: [exports.EngineState.INITIALIZING],
                [exports.EngineState.INITIALIZING]: [exports.EngineState.READY, exports.EngineState.IDLE],
                [exports.EngineState.READY]: [exports.EngineState.DETECTING, exports.EngineState.INITIALIZING],
                [exports.EngineState.DETECTING]: [exports.EngineState.READY, exports.EngineState.IDLE]
            };
            return validTransitions[from]?.includes(to) ?? false;
        }
        /**
         * Transition detection period state
         * @param newPeriod - Target detection period
         * @returns true if transition succeeded
         */
        transitionDetectionPeriod(newPeriod) {
            const oldPeriod = this.detectionState.period;
            if (oldPeriod === newPeriod)
                return true;
            this.detectionState.period = newPeriod;
            this.emitDebug('detection-period', 'Period transitioned', {
                from: oldPeriod,
                to: newPeriod
            }, 'info');
            return true;
        }
        /**
         * Partially reset detection state (keeps engine initialized)
         * Used when detection fails but engine should remain ready
         */
        partialResetDetectionState() {
            this.emitDebug('detection', 'Partial reset: Resetting detection state only');
            this.detectionState.reset();
            // Reset frame counters
            this.frameIndex = 0;
            // Keep Mat pool and canvas (they'll be reused)
            // Don't set isDetectingFrameActive = false here (let finally handle it)
        }
        /**
         * Fully reset detection state and resources
         * Used when stopping detection or reinitializing
         */
        fullResetDetectionState() {
            this.emitDebug('detection', 'Full reset: Resetting all detection resources');
            // Reset detection state (includes clearing large image buffers)
            try {
                this.detectionState.reset();
            }
            catch (error) {
                this.emitDebug('detection', 'Error resetting detection state', { error: error.message }, 'warn');
            }
            // Reset frame counters
            this.frameIndex = 0;
            // Clear frame canvas (releases memory)
            try {
                this.clearFrameCanvas();
            }
            catch (error) {
                this.emitDebug('detection', 'Error clearing frame canvas', { error: error.message }, 'warn');
            }
            // Clear preallocated Mat objects
            this.clearPreallocatedMats();
            // Ensure detection frame flag is cleared
            this.isDetectingFrameActive = false;
        }
        /**
         * Initialize the detection engine
         * Loads Human.js and OpenCV.js libraries
         *
         * @returns Promise that resolves when initialization is complete
         * @throws Error if initialization fails
         */
        async initialize() {
            if (this.engineState === exports.EngineState.INITIALIZING || this.engineState === exports.EngineState.READY || this.engineState === exports.EngineState.DETECTING) {
                return;
            }
            // Transition to INITIALIZING state
            if (!this.transitionEngineState(exports.EngineState.INITIALIZING, 'initialize() start')) {
                return;
            }
            this.emitDebug('initialization', 'Starting to load detection libraries...');
            try {
                // Load OpenCV
                this.emitDebug('initialization', 'Loading OpenCV...');
                const { cv } = await loadOpenCV(60000); // 1 minute timeout
                if (!cv || !cv.Mat) {
                    const cvError = {
                        code: exports.ErrorCode.DETECTOR_NOT_INITIALIZED,
                        message: 'Failed to load OpenCV.js: module is null or invalid'
                    };
                    this.emit('detector-error', cvError);
                    this.emitDebug('initialization', 'OpenCV loading failed: module is null or invalid', {}, 'error');
                    this.emit('detector-loaded', { success: false, error: cvError.message });
                    throw new Error(cvError.message);
                }
                this.cv = cv;
                const cv_version = getOpenCVVersion();
                this.emitDebug('initialization', 'OpenCV loaded successfully', { version: cv_version });
                console.log('[FaceDetectionEngine] OpenCV loaded successfully', { version: cv_version });
                // Load Human.js
                console.log('[FaceDetectionEngine] Loading Human.js models...');
                this.emitDebug('initialization', 'Loading Human.js...');
                const humanStartTime = performance.now();
                let loadError = null;
                try {
                    this.human = await loadHuman(this.options.human_model_path, this.options.tensorflow_wasm_path, this.options.tensorflow_backend);
                }
                catch (humanError) {
                    loadError = humanError;
                }
                if (loadError) {
                    const errorInfo = this.extractErrorInfo(loadError);
                    const errorMsg = errorInfo.message;
                    let errorContext = {
                        error: errorMsg,
                        stack: errorInfo.stack,
                        name: errorInfo.name,
                        cause: errorInfo.cause,
                        userAgent: navigator.userAgent,
                        platform: navigator.userAgentData?.platform || 'unknown',
                        browser: detectBrowserEngine(navigator.userAgent),
                        backend: this.options.tensorflow_backend,
                        source: 'human.js'
                    };
                    // Diagnostic hints
                    if (errorMsg.includes('inputs')) {
                        errorContext.diagnosis = 'Human.js internal error: Model structure incomplete';
                    }
                    else if (errorMsg.includes('timeout')) {
                        errorContext.diagnosis = 'Model loading timeout';
                    }
                    else if (errorMsg.includes('Critical models not loaded')) {
                        errorContext.diagnosis = 'Human.js failed to load required models';
                    }
                    else if (errorMsg.includes('empty')) {
                        errorContext.diagnosis = 'Models object is empty after loading';
                    }
                    else if (errorMsg.includes('incomplete')) {
                        errorContext.diagnosis = 'Models loaded but structure is incomplete';
                    }
                    console.error('[FaceDetectionEngine] Human.js loading failed:', errorContext);
                    this.emitDebug('initialization', 'Human.js loading failed', errorContext, 'error');
                    const errorEventData = {
                        code: exports.ErrorCode.DETECTOR_NOT_INITIALIZED,
                        message: `Human.js loading error: ${errorContext.diagnosis || errorMsg}`
                    };
                    this.emit('detector-loaded', { success: false, error: errorMsg, details: errorContext });
                    this.emit('detector-error', errorEventData);
                    throw new Error(errorMsg);
                }
                const humanLoadTime = performance.now() - humanStartTime;
                if (!this.human) {
                    const errorMsg = 'Failed to load Human.js: instance is null';
                    console.error('[FaceDetectionEngine] ' + errorMsg);
                    this.emitDebug('initialization', errorMsg, { loadTime: humanLoadTime }, 'error');
                    this.emit('detector-loaded', { success: false, error: errorMsg });
                    const errorEventData = {
                        code: exports.ErrorCode.DETECTOR_NOT_INITIALIZED,
                        message: errorMsg
                    };
                    this.emit('detector-error', errorEventData);
                    throw new Error(errorMsg);
                }
                // Verify Human.js instance has required properties
                if (!this.human.version || typeof this.human.detect !== 'function') {
                    const errorMsg = 'Human.js instance is incomplete: missing version or detect method';
                    console.error('[FaceDetectionEngine] ' + errorMsg);
                    this.emitDebug('initialization', errorMsg, {
                        hasVersion: !!this.human.version,
                        hasDetect: typeof this.human.detect === 'function',
                        instanceKeys: Object.keys(this.human || {})
                    }, 'error');
                    this.emit('detector-loaded', { success: false, error: errorMsg });
                    const errorEventData = {
                        code: exports.ErrorCode.DETECTOR_NOT_INITIALIZED,
                        message: errorMsg
                    };
                    this.emit('detector-error', errorEventData);
                    throw new Error(errorMsg);
                }
                this.emitDebug('initialization', 'Human.js loaded successfully', {
                    loadTime: `${humanLoadTime.toFixed(2)}ms`,
                    version: this.human.version,
                    backend: this.human.config?.backend || 'unknown'
                });
                console.log('[FaceDetectionEngine] Human.js loaded successfully', {
                    loadTime: `${humanLoadTime.toFixed(2)}ms`,
                    version: this.human.version
                });
                // Transition to READY state on success
                if (!this.transitionEngineState(exports.EngineState.READY, 'initialize() success')) {
                    throw new Error('Failed to transition to READY state');
                }
                const loadedData = {
                    success: true,
                    opencv_version: getOpenCVVersion(),
                    human_version: this.human.version
                };
                console.log('[FaceDetectionEngine] Engine initialized and ready', {
                    opencv_version: loadedData.opencv_version,
                    human_version: loadedData.human_version
                });
                this.emit('detector-loaded', loadedData);
                this.emitDebug('initialization', 'Engine initialized and ready', loadedData);
            }
            catch (error) {
                const errorInfo = this.extractErrorInfo(error);
                const errorMsg = errorInfo.message;
                // Transition back to IDLE on error
                this.transitionEngineState(exports.EngineState.IDLE, 'initialize() error');
                this.emit('detector-loaded', { success: false, error: errorMsg });
                this.emit('detector-error', {
                    code: exports.ErrorCode.DETECTOR_NOT_INITIALIZED,
                    message: errorMsg
                });
                this.emitDebug('initialization', 'Failed to load libraries', {
                    error: errorMsg,
                    stack: errorInfo.stack
                }, 'error');
            }
        }
        /**
         * Start face detection
         * Requires initialize() to be called first and a video element to be provided
         *
         * @param videoElement - HTMLVideoElement to capture from
         * @returns Promise that resolves when detection starts
         * @throws Error if not initialized or video setup fails
         */
        async startDetection(videoElement) {
            if (this.engineState !== exports.EngineState.READY) {
                this.emitDebug('detection', 'Engine not ready', { state: this.engineState }, 'warn');
                throw new Error('Engine not initialized. Call initialize() first.');
            }
            this.videoElement = videoElement;
            // Reset frame counters and detection state, but keep engine initialized
            // (fullResetDetectionState will be called in stopDetection on error)
            this.partialResetDetectionState();
            try {
                this.emitDebug('video-setup', 'Requesting camera access...');
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user',
                            width: { ideal: this.options.detect_video_ideal_width },
                            height: { ideal: this.options.detect_video_ideal_height },
                            aspectRatio: { ideal: this.options.detect_video_ideal_width / this.options.detect_video_ideal_height }
                        },
                        audio: false
                    });
                }
                catch (err) {
                    const error = err;
                    const isCameraAccessDenied = error.name === 'NotAllowedError' ||
                        error.name === 'PermissionDeniedError' ||
                        error.message.includes('Permission denied') ||
                        error.message.includes('Permission dismissed');
                    this.emitDebug('video-setup', 'Camera access failed', {
                        errorName: error.name,
                        errorMessage: error.message,
                        isCameraAccessDenied
                    }, 'error');
                    if (isCameraAccessDenied) {
                        this.emit('detector-error', {
                            code: exports.ErrorCode.CAMERA_ACCESS_DENIED,
                            message: 'Camera access denied by user'
                        });
                    }
                    else {
                        this.emit('detector-error', {
                            code: exports.ErrorCode.STREAM_ACQUISITION_FAILED,
                            message: error.name || 'UnknownError' + ": " + error.message || 'Unknown error message'
                        });
                    }
                    throw err;
                }
                if (!this.stream) {
                    throw new Error('Media stream is null');
                }
                // Set up video element
                this.videoElement.srcObject = this.stream;
                this.videoElement.autoplay = true;
                this.videoElement.playsInline = true;
                this.videoElement.muted = true;
                // Apply mirror effect if configured
                if (this.options.detect_video_mirror) {
                    this.videoElement.style.transform = 'scaleX(-1)';
                }
                const videoTrack = this.stream.getVideoTracks()[0];
                if (videoTrack) {
                    const settings = videoTrack.getSettings?.();
                    if (settings) {
                        if (settings.width && settings.height) {
                            this.actualVideoWidth = settings.width;
                            this.actualVideoHeight = settings.height;
                        }
                        const fps = settings.frameRate;
                        this.emitDebug('video-setup', 'Video stream resolution detected', {
                            width: this.actualVideoWidth,
                            height: this.actualVideoHeight,
                            fps: fps
                        });
                        if (fps) {
                            this.updateVideoFPS(fps);
                        }
                    }
                }
                this.emitDebug('video-setup', 'Camera access granted', {
                    trackCount: this.stream.getTracks().length
                });
                // Wait for video to be ready
                this.emitDebug('video-setup', 'Waiting for video to be ready...');
                await new Promise((resolve, reject) => {
                    const timeout = setTimeout(() => {
                        cleanup();
                        this.emit('detector-error', {
                            code: exports.ErrorCode.STREAM_ACQUISITION_FAILED,
                            message: 'Video loading timeout'
                        });
                        this.stopDetection(false);
                        reject(new Error('Video loading timeout'));
                    }, this.options.detect_video_load_timeout);
                    const onCanPlay = () => {
                        clearTimeout(timeout);
                        cleanup();
                        this.emitDebug('video-setup', 'Video is ready');
                        resolve();
                    };
                    const cleanup = () => {
                        if (this.videoElement) {
                            this.videoElement.removeEventListener('canplay', onCanPlay);
                        }
                    };
                    if (this.videoElement) {
                        this.videoElement.addEventListener('canplay', onCanPlay, { once: true });
                        this.videoElement.play().catch((err) => {
                            clearTimeout(timeout);
                            cleanup();
                            const errorInfo = this.extractErrorInfo(err);
                            this.emitDebug('video-setup', 'Failed to play video', {
                                error: errorInfo.message,
                                stack: errorInfo.stack,
                                name: errorInfo.name,
                                cause: errorInfo.cause
                            }, 'error');
                            reject(err);
                        });
                    }
                });
                // Transition to DETECTING state atomically
                if (!this.transitionEngineState(exports.EngineState.DETECTING, 'startDetection() video ready')) {
                    throw new Error('Failed to transition to DETECTING state');
                }
                this.cancelPendingDetection();
                this.animationFrameId = requestAnimationFrame(() => {
                    this.detect();
                });
                // Mat objects will be created per-frame on demand
                this.emitDebug('video-setup', 'Detection started');
            }
            catch (error) {
                const errorInfo = this.extractErrorInfo(error);
                const errorMsg = errorInfo.message;
                this.emitDebug('video-setup', 'Failed to start detection', {
                    error: errorMsg,
                    stack: errorInfo.stack,
                    name: errorInfo.name,
                    cause: errorInfo.cause
                }, 'error');
                this.emit('detector-error', {
                    code: exports.ErrorCode.STREAM_ACQUISITION_FAILED,
                    message: errorMsg
                });
                this.stopDetection(false);
            }
        }
        /**
         * Stop face detection
         * Performs comprehensive cleanup to prevent memory leaks and UI freezing
         * @param success - Whether to display the best collected image
         */
        stopDetection(success) {
            this.emitDebug('detection', 'stopDetection called', {
                success,
                engineState: this.engineState,
                isDetectingFrameActive: this.isDetectingFrameActive,
                hasPendingFrame: this.animationFrameId !== null
            }, 'info');
            // Step 1: Stop the animation frame immediately
            this.cancelPendingDetection();
            // Step 2: Transition state - force transition to prevent detect() finally from rescheduling
            // Use direct assignment instead of transitionEngineState to bypass validation
            // This ensures no animationFrame gets scheduled after cancellation
            const prevState = this.engineState;
            if (prevState === exports.EngineState.DETECTING) {
                this.engineState = exports.EngineState.READY;
                this.emitDebug('state-management', 'State transitioned (forced stop)', {
                    from: prevState,
                    to: exports.EngineState.READY,
                    context: 'stopDetection()'
                }, 'info');
            }
            // Step 3: Prepare finish data (before clearing images)
            const finishData = {
                success: success,
                silentPassedCount: this.detectionState.collectCount,
                actionPassedCount: this.detectionState.completedActions.size,
                totalTime: performance.now() - this.detectionState.startTime,
                bestQualityScore: this.detectionState.bestQualityScore,
                bestFrameImage: this.detectionState.bestFrameImage,
                bestFaceImage: this.detectionState.bestFaceImage
            };
            // Step 4: Emit finish event (emit immediately, before any cleanup)
            try {
                this.emit('detector-finish', finishData);
            }
            catch (error) {
                this.emitDebug('detection', 'Error emitting detector-finish event', { error: error.message }, 'error');
            }
            // Step 5: Stop video playback
            if (this.videoElement) {
                try {
                    // 检查是否已经暂停，避免重复操作
                    if (!this.videoElement.paused) {
                        this.videoElement.pause();
                    }
                }
                catch (error) {
                    this.emitDebug('detection', 'Error pausing video', { error: error.message }, 'warn');
                }
            }
            // Step 6: Stop and release media stream tracks
            if (this.stream) {
                try {
                    this.stream.getTracks().forEach(track => {
                        try {
                            // 可在stop()前检查轨道状态
                            if (track.readyState === 'live') {
                                track.stop();
                            }
                        }
                        catch (trackError) {
                            this.emitDebug('detection', 'Error stopping media track', {
                                error: trackError.message,
                                trackKind: track.kind
                            }, 'warn');
                        }
                    });
                }
                catch (streamError) {
                    this.emitDebug('detection', 'Error processing media stream', { error: streamError.message }, 'warn');
                }
                this.stream = null;
            }
            // Step 7: Disconnect video element from stream
            if (this.videoElement) {
                try {
                    this.videoElement.srcObject = null;
                }
                catch (error) {
                    this.emitDebug('detection', 'Error clearing video element', { error: error.message }, 'warn');
                }
            }
            // Step 8: Full cleanup of detection state
            this.fullResetDetectionState();
            this.emitDebug('detection', 'Detection stopped completely (FINISH)', { success });
        }
        /**
         * Get current configuration
         * @returns Current configuration
         */
        getOptions() {
            return { ...this.options };
        }
        // ==================== Private Methods ====================
        updateVideoFPS(fps) {
            if (this.videoFPS === fps)
                return;
            console.log(`[FaceDetectionEngine] Video FPS changed: ${this.videoFPS} -> ${fps}`);
            this.videoFPS = fps;
        }
        /**
         * Cancel pending detection frame
         */
        cancelPendingDetection() {
            if (this.animationFrameId !== null) {
                cancelAnimationFrame(this.animationFrameId);
                this.animationFrameId = null;
            }
        }
        /**
         * Main detection loop
         * Called every frame via requestAnimationFrame
         * Orchestrates the detection pipeline with clear separation of concerns
         */
        async detect() {
            // 防止并发调用
            if (this.isDetectingFrameActive) {
                this.emitDebug('detection', '检测帧正在处理中，跳过本帧', {}, 'info');
                return;
            }
            // 状态和前置条件检查
            if (this.engineState !== exports.EngineState.DETECTING) {
                this.emitDebug('detection', '引擎状态不是DETECTING，无法继续检测', {
                    currentState: this.engineState,
                    expectedState: exports.EngineState.DETECTING
                }, 'warn');
                return;
            }
            if (!this.videoElement) {
                this.emitDebug('detection', '视频元素未初始化', {}, 'error');
                return;
            }
            if (!this.human) {
                this.emitDebug('detection', 'Human.js实例未初始化', {}, 'error');
                return;
            }
            if (this.videoElement.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
                this.emitDebug('detection', '视频尚未准备好，readyState不足', {
                    readyState: this.videoElement.readyState,
                    requiredState: HTMLMediaElement.HAVE_CURRENT_DATA
                }, 'info');
                return;
            }
            // 设置检测帧活跃标志
            this.isDetectingFrameActive = true;
            this.frameIndex++;
            this.emitDebug('detection', '进入检测帧循环', {
                frameIndex: this.frameIndex,
                period: this.detectionState.period,
                engineState: this.engineState,
                videoReadyState: this.videoElement.readyState
            }, 'info');
            try {
                // 执行人脸检测
                await this.performFaceDetection();
            }
            catch (error) {
                const errorInfo = this.extractErrorInfo(error);
                this.emitDebug('detection', 'Unexpected error in detection loop', {
                    error: errorInfo.message,
                    stack: errorInfo.stack,
                    name: errorInfo.name,
                    cause: errorInfo.cause
                }, 'error');
            }
            finally {
                // 清除检测帧活跃标志
                this.isDetectingFrameActive = false;
                // 调度下一帧的检测（仅当引擎仍在检测状态时）
                if (this.engineState === exports.EngineState.DETECTING) {
                    this.animationFrameId = requestAnimationFrame(() => {
                        this.detect();
                    });
                }
            }
        }
        /**
         * Capture video frame and convert to BGR and Grayscale Mat objects
         * @returns {Object | null} Object with bgrFrame and grayFrame, or null if failed
         */
        captureAndPrepareFrames() {
            const frameCapturStartTime = performance.now();
            // Draw video frame to canvas
            const frameCanvas = this.drawVideoToCanvas();
            if (!frameCanvas) {
                this.emitDebug('detection', 'Failed to draw video frame to canvas', {}, 'warn');
                return null;
            }
            // Ensure preallocated Mat objects exist (created once, reused)
            if (!this.preallocatedBgrFrame || !this.preallocatedGrayFrame) {
                this.ensurePreallocatedMats();
                if (!this.preallocatedBgrFrame || !this.preallocatedGrayFrame) {
                    this.emitDebug('detection', 'Failed to create preallocated Mat objects', {}, 'error');
                    return null;
                }
            }
            // Copy canvas ImageData to preallocated BGR Mat using helper function
            const bgrFrame = drawCanvasToMat(this.cv, frameCanvas, this.preallocatedBgrFrame);
            if (!bgrFrame) {
                this.emitDebug('detection', 'Failed to copy canvas data to BGR Mat', {}, 'warn');
                return null;
            }
            const bgrFrameTime = performance.now() - frameCapturStartTime;
            // Convert BGR to grayscale (reuse preallocated Mat)
            const grayConversionStartTime = performance.now();
            try {
                this.cv.cvtColor(bgrFrame, this.preallocatedGrayFrame, this.cv.COLOR_RGBA2GRAY);
            }
            catch (cvtError) {
                this.emitDebug('detection', 'cvtColor failed', { error: cvtError.message }, 'warn');
                return null;
            }
            const grayConversionTime = performance.now() - grayConversionStartTime;
            const grayFrame = this.preallocatedGrayFrame;
            // Log performance metrics if slow
            const totalFrameProcessingTime = bgrFrameTime + grayConversionTime;
            if (totalFrameProcessingTime > 50) {
                this.emitDebug('performance', 'Frame capture slow', {
                    bgrFrameCapture: bgrFrameTime.toFixed(2) + 'ms',
                    grayConversion: grayConversionTime.toFixed(2) + 'ms',
                    total: totalFrameProcessingTime.toFixed(2) + 'ms',
                    videoResolution: `${this.actualVideoWidth}x${this.actualVideoHeight}`
                }, 'warn');
            }
            return { bgrFrame, grayFrame };
        }
        /**
         * Perform main face detection and handle results
         */
        async performFaceDetection() {
            // Perform face detection
            const timestamp = performance.now();
            let result;
            try {
                result = await this.human?.detect(this.videoElement);
                if (!result) {
                    this.emitDebug('detection', 'Face detection returned null result', {}, 'warn');
                    return;
                }
            }
            catch (detectError) {
                const errorInfo = this.extractErrorInfo(detectError);
                this.emitDebug('detection', 'Human.detect() call failed', {
                    error: errorInfo.message,
                    stack: errorInfo.stack,
                    name: errorInfo.name,
                    hasHuman: !!this.human,
                    humanVersion: this.human?.version,
                    videoReadyState: this.videoElement?.readyState,
                    videoWidth: this.videoElement?.videoWidth,
                    videoHeight: this.videoElement?.videoHeight
                }, 'error');
                return;
            }
            const faces = result.face || [];
            const gestures = result.gesture || [];
            if (faces.length === 1) {
                this.handleSingleFace(faces[0], gestures, timestamp);
            }
            else {
                this.handleMultipleFaces(faces.length);
            }
        }
        getPerformActionCount() {
            if (this.options.action_liveness_action_count <= 0) {
                this.emitDebug('config', 'liveness_action_count is 0 or negative', { count: this.options.action_liveness_action_count }, 'info');
                return 0;
            }
            const actionListLength = this.options.action_liveness_action_list?.length ?? 0;
            if (actionListLength === 0) {
                this.emitDebug('config', 'liveness_action_list is empty', { actionListLength }, 'info');
            }
            return Math.min(this.options.action_liveness_action_count, actionListLength);
        }
        /**
         * Handle single face detection
         */
        handleSingleFace(face, gestures, timestamp) {
            const faceBox = face.box || face.boxRaw;
            if (!faceBox) {
                console.warn('[FaceDetector] Face detected but no box/boxRaw property');
                this.emitDebug('detection', 'Face box is missing - face detected but no box/boxRaw property', {}, 'warn');
                return;
            }
            if (!this.detectionState.faceMovingDetector) {
                this.emit('detector-error', {
                    code: exports.ErrorCode.INTERNAL_ERROR,
                    message: 'Face moving detector is not initialized'
                });
                // Clear the detecting flag before stopping to avoid deadlock
                this.isDetectingFrameActive = false;
                this.stopDetection(false);
                return;
            }
            if (!this.detectionState.photoAttackDetector) {
                this.emit('detector-error', {
                    code: exports.ErrorCode.INTERNAL_ERROR,
                    message: 'Photo attack detector is not initialized'
                });
                // Clear the detecting flag before stopping to avoid deadlock
                this.isDetectingFrameActive = false;
                this.stopDetection(false);
                return;
            }
            try {
                // 动作活体检测阶段处理
                if (this.detectionState.period === exports.DetectionPeriod.VERIFY) {
                    this.handleVerifyPhase(gestures);
                    return;
                }
                // 面部区域占比计算
                const faceRatio = (faceBox[2] * faceBox[3]) / (this.actualVideoWidth * this.actualVideoHeight);
                // 面部区域过小则跳过当前帧
                if (faceRatio <= this.options.collect_min_face_ratio) {
                    this.emitDetectorInfo({ code: exports.DetectionCode.FACE_TOO_SMALL, faceRatio: faceRatio });
                    this.emitDebug('detection', 'Face is too small', { ratio: faceRatio.toFixed(4), minRatio: this.options.collect_min_face_ratio, maxRatio: this.options.collect_max_face_ratio }, 'info');
                    return;
                }
                // 面部区域过大则跳过当前帧
                if (faceRatio >= this.options.collect_max_face_ratio) {
                    this.emitDetectorInfo({ code: exports.DetectionCode.FACE_TOO_LARGE, faceRatio: faceRatio });
                    this.emitDebug('detection', 'Face is too large', { ratio: faceRatio.toFixed(4), minRatio: this.options.collect_min_face_ratio, maxRatio: this.options.collect_max_face_ratio }, 'info');
                    return;
                }
                // 开启面部移动检测
                if (this.options.enable_face_moving_detection) {
                    this.detectionState.faceMovingDetector.addFrame(face, timestamp);
                    const faceMovingResult = this.detectionState.faceMovingDetector.detect();
                    if (faceMovingResult.available) {
                        if (!faceMovingResult.isMoving) {
                            // 面部移动检测失败，可能为照片攻击
                            this.emitDebug('motion-detection', 'Face moving detection failed - possible photo attack', faceMovingResult.details, 'warn');
                            this.emitDetectorInfo({
                                code: exports.DetectionCode.FACE_NOT_MOVING,
                                message: faceMovingResult.getMessage(),
                            });
                            this.partialResetDetectionState();
                            return;
                        }
                    }
                }
                // 开启照片攻击检测
                if (this.options.enable_photo_attack_detection) {
                    this.detectionState.photoAttackDetector.addFrame(face);
                    const photoAttackResult = this.detectionState.photoAttackDetector.detect();
                    if (photoAttackResult.available) {
                        // 照片攻击检测可用（仅当判定为照片攻击时)
                        if (photoAttackResult.isPhoto) {
                            this.emitDetectorInfo({
                                code: exports.DetectionCode.PHOTO_ATTACK_DETECTED,
                                message: photoAttackResult.getMessage(),
                            });
                            this.emitDebug('motion-detection', 'Photo attack detected', photoAttackResult.details, 'warn');
                            this.partialResetDetectionState();
                            return;
                        }
                        else {
                            if (photoAttackResult.trusted) {
                                // 仅当采集到足够帧，且判定为非照片攻击时，才采信
                                this.detectionState.liveness = true;
                                this.emitDebug('motion-detection', 'Photo attack detection passed - face is live', photoAttackResult.details, 'warn');
                            }
                        }
                    }
                }
                else {
                    // 未启用照片攻击检测，默认活体为活体
                    this.detectionState.liveness = true;
                }
                // 捕获并准备帧数据
                const frameData = this.captureAndPrepareFrames();
                if (!frameData) {
                    this.emitDebug('detection', '帧采集失败，无法继续检测', {
                        frameIndex: this.frameIndex
                    }, 'warn');
                    return;
                }
                const bgrFrame = frameData.bgrFrame;
                const grayFrame = frameData.grayFrame;
                let frontal = 1;
                // 计算面部正对度，不达标则跳过当前帧
                if (this.detectionState.needFrontalFace()) {
                    frontal = calcFaceFrontal(this.cv, face, gestures, grayFrame, this.options.collect_face_frontal_features);
                    this.detectionState.lastFrontalScore = frontal;
                    if (frontal < this.options.collect_min_face_frontal) {
                        this.emitDetectorInfo({ code: exports.DetectionCode.FACE_NOT_FRONTAL, faceRatio: faceRatio, faceFrontal: frontal });
                        this.emitDebug('detection', 'Face is not frontal to camera', { frontal: frontal.toFixed(4), minFrontal: this.options.collect_min_face_frontal }, 'info');
                        return;
                    }
                }
                // 计算图像质量分数，不达标则跳过当前帧
                const qualityResult = calcImageQuality(this.cv, grayFrame, this.options.collect_image_quality_features, this.options.collect_min_image_quality);
                if (!qualityResult.passed || qualityResult.score < this.options.collect_min_image_quality) {
                    this.emitDetectorInfo({ code: exports.DetectionCode.FACE_LOW_QUALITY, faceRatio: faceRatio, faceFrontal: frontal, imageQuality: qualityResult.score });
                    this.emitDebug('detection', 'Image quality does not meet requirements', {
                        score: qualityResult.score,
                        passed: qualityResult.passed,
                        minRequired: this.options.collect_min_image_quality,
                        frameSize: grayFrame?.cols && grayFrame?.rows ? `${grayFrame.cols}x${grayFrame.rows}` : 'unknown'
                        // 移除了冗余的帧信息和 qualityResult 完整对象，避免大数据输出
                    }, 'warn');
                    return;
                }
                // 当前帧通过常规检查
                this.emitDetectorInfo({ passed: true, code: exports.DetectionCode.FACE_IMAGE_CAPTURED, faceRatio: faceRatio, faceFrontal: frontal, imageQuality: qualityResult.score });
                // 检测阶段，图像各方面合规，进入采集阶段
                if (this.detectionState.period === exports.DetectionPeriod.DETECT) {
                    this.handleDetectPhase();
                }
                // 采集阶段，采集当前帧图像
                if (this.detectionState.period === exports.DetectionPeriod.COLLECT) {
                    this.handleCollectPhase(bgrFrame, qualityResult.score, faceBox);
                }
                // 采集到足够的图像，并且静默活体通过，进入动作验证阶段
                if (this.detectionState.isReadyToVerify(this.options.collect_min_collect_count)) {
                    this.emitDebug('detection', 'Ready to enter action verification phase', {
                        collectCount: this.detectionState.collectCount,
                        minCollectCount: this.options.collect_min_collect_count
                    });
                    if (this.getPerformActionCount() > 0) {
                        this.transitionDetectionPeriod(exports.DetectionPeriod.VERIFY);
                        this.emitDebug('detection', 'Entering action verification phase');
                    }
                    else {
                        // 如果没有动作检测需求，则直接完成检测
                        this.emitDebug('detection', 'No action verification required, finishing detection as successful');
                        this.stopDetection(true);
                    }
                    return;
                }
            }
            catch (error) {
                const errorInfo = this.extractErrorInfo(error);
                const errorMsg = errorInfo.message;
                this.emitDebug('detection', 'Unexpected error in single face handling', {
                    error: errorMsg,
                    stack: errorInfo.stack,
                    name: errorInfo.name,
                    cause: errorInfo.cause
                }, 'error');
            }
        }
        /**
         * Handle detect phase
         */
        handleDetectPhase() {
            this.transitionDetectionPeriod(exports.DetectionPeriod.COLLECT);
            this.emitDebug('detection', 'Entering image collection phase');
        }
        /**
         * Handle collect phase
         */
        handleCollectPhase(bgrFrame, qualityScore, faceBox) {
            this.collectHighQualityImage(bgrFrame, qualityScore, faceBox);
        }
        /**
         * 验证动作阶段处理
         * @param gestures - Detected gestures from Human.js
         */
        handleVerifyPhase(gestures) {
            if (this.detectionState.currentAction === null) {
                // 当前无动作，选择下一个动作
                if (!this.selectNextAction()) {
                    // 下一个动作不可用，内部错误
                    this.emit('detector-error', {
                        code: exports.ErrorCode.INTERNAL_ERROR,
                        message: 'No available actions to perform for liveness verification'
                    });
                    this.stopDetection(false);
                    return;
                }
                return;
            }
            // 检测实际动作
            const detectedActions = this.detectAction(gestures);
            if (detectedActions.length === 0) {
                // 没有任何动作，继续检测
                return;
            }
            // 验证检测到的动作：只有检测到期望的动作才算成功
            // 如果同时检测到多个动作（如NOD_DOWN和NOD_UP），只要包含期望的动作即可
            if (!detectedActions.includes(this.detectionState.currentAction)) {
                this.emitDebug('action-verify', 'Action mismatch', {
                    expected: this.detectionState.currentAction,
                    detected: detectedActions
                }, 'warn');
                // this.emit('detector-action' as any, {
                //   action: this.detectionState.currentAction,
                //   detected: detectedActions,
                //   status: LivenessActionStatus.MISMATCH
                // })
                // this.stopDetection(false)
                return;
            }
            // 动作验证成功
            this.emit('detector-action', {
                action: this.detectionState.currentAction,
                detected: detectedActions,
                status: exports.LivenessActionStatus.COMPLETED
            });
            this.emitDebug('action-verify', 'Action detected', { action: this.detectionState.currentAction });
            this.detectionState.onActionCompleted();
            // 检查是否完成所有动作
            if (this.detectionState.completedActions.size >= this.getPerformActionCount()) {
                this.stopDetection(true);
                return;
            }
            // 选择下一个动作
            if (!this.selectNextAction()) {
                this.emit('detector-error', {
                    code: exports.ErrorCode.INTERNAL_ERROR,
                    message: 'No available actions to perform for liveness verification'
                });
                this.stopDetection(false);
            }
        }
        /**
         * Handle multiple or no faces
         */
        handleMultipleFaces(faceCount) {
            if (faceCount === 0) {
                this.emitDetectorInfo({ code: exports.DetectionCode.VIDEO_NO_FACE, faceCount: 0 });
            }
            else if (faceCount > 1) {
                this.emitDetectorInfo({ code: exports.DetectionCode.MULTIPLE_FACE, faceCount: faceCount });
            }
            if (this.detectionState.period !== exports.DetectionPeriod.DETECT) {
                this.emitDebug('detection', 'Multiple or no faces detected, resetting detection state', { faceCount });
                this.partialResetDetectionState();
            }
        }
        collectHighQualityImage(bgrFrame, frameQuality, faceBox) {
            if (frameQuality <= this.detectionState.bestQualityScore) {
                // Current frame quality is not better than saved best frame, skip without saving
                this.detectionState.collectCount++;
                return;
            }
            try {
                const frameImageData = matToBase64Jpeg(this.cv, bgrFrame);
                if (!frameImageData) {
                    this.emitDebug('detection', 'Failed to capture current frame image', { frameQuality, bestQualityScore: this.detectionState.bestQualityScore }, 'warn');
                    return;
                }
                const faceMat = bgrFrame.roi(new this.cv.Rect(faceBox[0], faceBox[1], faceBox[2], faceBox[3]));
                const faceImageData = matToBase64Jpeg(this.cv, faceMat);
                faceMat.delete();
                if (!faceImageData) {
                    this.emitDebug('detection', 'Failed to capture face image', { faceBox }, 'warn');
                    return;
                }
                this.detectionState.collectCount++;
                this.detectionState.bestQualityScore = frameQuality;
                this.detectionState.bestFrameImage = frameImageData;
                this.detectionState.bestFaceImage = faceImageData;
                this.emitDebug('detection', 'Collected high-quality image frame', {
                    collectCount: this.detectionState.collectCount,
                    bestQualityScore: this.detectionState.bestQualityScore,
                    bestFrameImageSize: frameImageData.length,
                    bestFaceImageSize: faceImageData.length
                }, 'warn');
            }
            catch (error) {
                this.emitDebug('detection', 'Error during image collection', { error: error.message }, 'error');
            }
        }
        emitDetectorInfo(params) {
            this.emit('detector-info', {
                passed: params.passed ?? false,
                code: params.code,
                message: params.message ?? '',
                faceCount: params.faceCount ?? 1,
                faceRatio: params.faceRatio ?? 0,
                faceFrontal: params.faceFrontal ?? 0,
                imageQuality: params.imageQuality ?? 0,
                motionScore: params.motionScore ?? 0,
                keypointVariance: params.keypointVariance ?? 0,
                motionType: params.motionType ?? '',
                screenConfidence: params.screenConfidence ?? 0
            });
        }
        /**
         * Select next action
         */
        selectNextAction() {
            const availableActions = (this.options.action_liveness_action_list ?? []).filter(action => !this.detectionState.completedActions.has(action));
            if (availableActions.length === 0) {
                this.emitDebug('action-verify', 'No available actions to perform', { completedActions: Array.from(this.detectionState.completedActions), totalActions: this.options.action_liveness_action_list?.length ?? 0 }, 'warn');
                return false;
            }
            let nextAction = availableActions[0];
            if (this.options.action_liveness_action_randomize) {
                // Random selection
                const randomIndex = Math.floor(Math.random() * availableActions.length);
                nextAction = availableActions[randomIndex];
            }
            const actionStart = {
                action: nextAction,
                detected: [],
                status: exports.LivenessActionStatus.STARTED
            };
            this.emit('detector-action', actionStart);
            this.emitDebug('action-verify', 'Action selected', { action: nextAction }, 'warn');
            this.detectionState.onActionStarted(nextAction, this.options.action_liveness_verify_timeout, () => {
                this.emitDebug('action-verify', 'Action verify timeout', {
                    action: nextAction,
                    timeout: this.options.action_liveness_verify_timeout
                }, 'warn');
                this.emit('detector-action', {
                    action: nextAction,
                    detected: [],
                    status: exports.LivenessActionStatus.TIMEOUT
                });
                this.partialResetDetectionState();
            });
            return true;
        }
        /**
         * Detect all actions from gestures
         * @returns Array of detected actions, empty array if none detected
         */
        detectAction(gestures) {
            const detectedActions = [];
            if (!gestures || gestures.length === 0) {
                this.emitDebug('action-verify', 'No gestures detected for action verification', { gestureCount: gestures.length ?? 0 }, 'warn');
                return detectedActions;
            }
            try {
                // Check for BLINK - look for blink or eye-related gestures
                if (gestures.some(g => {
                    if (!g.gesture)
                        return false;
                    const gestureStr = g.gesture.toLowerCase();
                    return gestureStr.includes('blink') || gestureStr.includes('eye') && gestureStr.includes('close');
                })) {
                    detectedActions.push(exports.LivenessAction.BLINK);
                }
                // Check for MOUTH_OPEN - look for mouth opening gestures
                if (gestures.some(g => {
                    const gestureStr = g.gesture;
                    if (!gestureStr)
                        return false;
                    const lowerStr = gestureStr.toLowerCase();
                    // Check for mouth/lip/jaw related keywords
                    const hasMouthReference = lowerStr.includes('mouth') || lowerStr.includes('lip') || lowerStr.includes('jaw');
                    // Check for open/opening related keywords
                    const isOpening = lowerStr.includes('open') || lowerStr.includes('opening');
                    // Check for percentage if available
                    const percentMatch = lowerStr.match(/(\d+)%/);
                    if (hasMouthReference && isOpening) {
                        if (percentMatch && percentMatch[1]) {
                            const percentValue = parseInt(percentMatch[1]) / 100;
                            return percentValue >= (this.options.action_liveness_min_mouth_open_percent || 0.2);
                        }
                        // If no percentage but mentions mouth opening, accept it
                        return true;
                    }
                    return false;
                })) {
                    detectedActions.push(exports.LivenessAction.MOUTH_OPEN);
                }
                // Check for NOD_DOWN (head down) - look for head/neck rotation down
                if (gestures.some(g => {
                    if (!g.gesture)
                        return false;
                    const gestureStr = g.gesture.toLowerCase();
                    // Check for head rotation patterns indicating nod down
                    return gestureStr.includes('pitch') && gestureStr.includes('-') ||
                        gestureStr.includes('head') && gestureStr.includes('down') ||
                        gestureStr.includes('neck') && gestureStr.includes('rotate') && gestureStr.includes('down') ||
                        gestureStr.includes('look') && gestureStr.includes('down');
                })) {
                    detectedActions.push(exports.LivenessAction.NOD_DOWN);
                }
                // Check for NOD_UP (head up) - look for head/neck rotation up
                if (gestures.some(g => {
                    if (!g.gesture)
                        return false;
                    const gestureStr = g.gesture.toLowerCase();
                    // Check for head rotation patterns indicating nod up
                    return gestureStr.includes('pitch') && gestureStr.includes('+') ||
                        gestureStr.includes('head') && gestureStr.includes('up') ||
                        gestureStr.includes('neck') && gestureStr.includes('rotate') && gestureStr.includes('up') ||
                        gestureStr.includes('look') && gestureStr.includes('up');
                })) {
                    detectedActions.push(exports.LivenessAction.NOD_UP);
                }
                if (detectedActions.length > 0) {
                    this.emitDebug('action-verify', 'Actions detected', { detectedActions }, 'warn');
                }
            }
            catch (error) {
                this.emitDebug('action-verify', 'Error during action detection', { error: error.message }, 'error');
            }
            return detectedActions;
        }
        /**
         * Emit debug event
         */
        emitDebug(stage, message, details, level = 'info') {
            if (this.options.debug_mode !== true)
                return;
            // 日志级别过滤
            const configuredLevel = this.options.debug_log_level || 'info';
            if (this.debugLogLevelPriority[level] < this.debugLogLevelPriority[configuredLevel]) {
                return;
            }
            // 阶段过滤
            if (this.options.debug_log_stages && this.options.debug_log_stages.length > 0) {
                if (!this.options.debug_log_stages.includes(stage)) {
                    return;
                }
            }
            // 节流机制（仅对 info 级别日志）
            if (level === 'info' && this.options.debug_log_throttle && this.options.debug_log_throttle > 0) {
                const throttleKey = `${stage}:${message}`;
                const now = Date.now();
                const lastTime = this.lastDebugLogTime.get(throttleKey) || 0;
                if (now - lastTime < this.options.debug_log_throttle) {
                    return; // 在节流时间内，跳过
                }
                this.lastDebugLogTime.set(throttleKey, now);
            }
            const debugData = {
                level,
                stage,
                message,
                details,
                timestamp: Date.now()
            };
            this.emit('detector-debug', debugData);
        }
        /**
         * Draw video frame to canvas (internal use, not converted to Base64)
         * Handles potential runtime resolution changes from camera stream
         * @returns {HTMLCanvasElement | null} Canvas after drawing, returns null if failed
         */
        drawVideoToCanvas() {
            try {
                if (!this.videoElement)
                    return null;
                // Check actual video resolution every frame (accounts for runtime changes)
                const currentVideoWidth = this.videoElement.videoWidth;
                const currentVideoHeight = this.videoElement.videoHeight;
                // Update cached resolution if initial or changed
                if (this.actualVideoWidth <= 0 || this.actualVideoHeight <= 0 ||
                    this.actualVideoWidth !== currentVideoWidth ||
                    this.actualVideoHeight !== currentVideoHeight) {
                    const resolutionChanged = this.actualVideoWidth > 0 &&
                        (this.actualVideoWidth !== currentVideoWidth ||
                            this.actualVideoHeight !== currentVideoHeight);
                    if (resolutionChanged) {
                        this.emitDebug('capture', 'Video resolution changed at runtime', {
                            oldResolution: `${this.actualVideoWidth}x${this.actualVideoHeight}`,
                            newResolution: `${currentVideoWidth}x${currentVideoHeight}`
                        }, 'warn');
                    }
                    this.actualVideoWidth = currentVideoWidth;
                    this.actualVideoHeight = currentVideoHeight;
                    this.clearFrameCanvas();
                }
                // If cached canvas size does not match actual resolution, recreate it
                if (!this.frameCanvasElement ||
                    this.frameCanvasElement.width !== this.actualVideoWidth ||
                    this.frameCanvasElement.height !== this.actualVideoHeight) {
                    this.clearFrameCanvas();
                    // Clear preallocated Mats since resolution changed
                    this.clearPreallocatedMats();
                    this.frameCanvasElement = document.createElement('canvas');
                    this.frameCanvasElement.width = this.actualVideoWidth;
                    this.frameCanvasElement.height = this.actualVideoHeight;
                    this.frameCanvasContext = this.frameCanvasElement.getContext('2d');
                    this.emitDebug('capture', 'Canvas created/resized', {
                        width: this.actualVideoWidth,
                        height: this.actualVideoHeight,
                        timestamp: Date.now()
                    });
                }
                if (!this.frameCanvasContext)
                    return null;
                // Before attempting to draw, verify video drawability
                if (this.videoElement.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
                    this.emitDebug('capture', 'Video not ready for frame capture', {
                        readyState: this.videoElement.readyState,
                        HAVE_CURRENT_DATA: HTMLMediaElement.HAVE_CURRENT_DATA
                    }, 'warn');
                    return null;
                }
                this.frameCanvasContext.drawImage(this.videoElement, 0, 0, this.actualVideoWidth, this.actualVideoHeight);
                // 帧绘制成功日志已移除，减少高频输出
                return this.frameCanvasElement;
            }
            catch (e) {
                this.emitDebug('capture', 'Failed to draw frame to canvas', { error: e.message }, 'error');
                return null;
            }
        }
        clearFrameCanvas() {
            if (this.frameCanvasContext) {
                try {
                    this.frameCanvasContext.clearRect(0, 0, this.frameCanvasElement?.width ?? 0, this.frameCanvasElement?.height ?? 0);
                }
                catch (error) {
                    this.emitDebug('detection', 'Error clearing canvas context', { error: error.message }, 'warn');
                }
                this.frameCanvasContext = null;
            }
            if (this.frameCanvasElement) {
                try {
                    this.frameCanvasElement.width = 0;
                    this.frameCanvasElement.height = 0;
                }
                catch (error) {
                    this.emitDebug('detection', 'Error resizing canvas', { error: error.message }, 'warn');
                }
                this.frameCanvasElement = null;
            }
        }
        /**
         * Ensure preallocated Mat objects exist with correct dimensions
         * Creates both BGR and Gray Mat for reuse
         */
        ensurePreallocatedMats() {
            if (!this.cv || this.actualVideoWidth <= 0 || this.actualVideoHeight <= 0) {
                return;
            }
            try {
                // Create BGR Mat if not exists (RGBA format from canvas ImageData)
                if (!this.preallocatedBgrFrame) {
                    this.preallocatedBgrFrame = new this.cv.Mat(this.actualVideoHeight, this.actualVideoWidth, this.cv.CV_8UC4 // RGBA format from canvas
                    );
                    this.emitDebug('capture', 'Created preallocated BGR Mat', {
                        width: this.actualVideoWidth,
                        height: this.actualVideoHeight
                    });
                }
                // Create grayscale Mat if not exists
                if (!this.preallocatedGrayFrame) {
                    this.preallocatedGrayFrame = new this.cv.Mat(this.actualVideoHeight, this.actualVideoWidth, this.cv.CV_8UC1 // Grayscale single channel
                    );
                    this.emitDebug('capture', 'Created preallocated Gray Mat', {
                        width: this.actualVideoWidth,
                        height: this.actualVideoHeight
                    });
                }
            }
            catch (error) {
                this.emitDebug('capture', 'Failed to create preallocated Mats', {
                    error: error.message
                }, 'error');
                this.clearPreallocatedMats();
            }
        }
        /**
         * Clear preallocated Mat objects
         * Called when resolution changes or detection stops
         */
        clearPreallocatedMats() {
            try {
                if (this.preallocatedBgrFrame) {
                    this.preallocatedBgrFrame.delete();
                    this.preallocatedBgrFrame = null;
                    this.emitDebug('capture', 'Cleared preallocated BGR Mat');
                }
            }
            catch (error) {
                this.emitDebug('capture', 'Error clearing preallocated BGR Mat', {
                    error: error.message
                }, 'warn');
                this.preallocatedBgrFrame = null;
            }
            try {
                if (this.preallocatedGrayFrame) {
                    this.preallocatedGrayFrame.delete();
                    this.preallocatedGrayFrame = null;
                    this.emitDebug('capture', 'Cleared preallocated Gray Mat');
                }
            }
            catch (error) {
                this.emitDebug('capture', 'Error clearing preallocated Gray Mat', {
                    error: error.message
                }, 'warn');
                this.preallocatedGrayFrame = null;
            }
        }
    }

    /**
     * Face Detection Engine - UniApp Resource Manager
     * Handles loading of models and WASM files in UniApp environment
     */
    /**
     * 获取资源路径（考虑 static 目录）
     * 支持 UniApp App、H5、小程序等多个平台
     * @param relativePath - 相对路径（相对于插件的 static 目录）
     * @returns 完整的资源路径
     */
    function getResourcePath(relativePath) {
        // 优先使用 UniApp 官方方式判断平台
        try {
            const uni = globalThis.uni;
            const systemInfo = uni?.getSystemInfoSync?.();
            if (systemInfo?.uniPlatform) {
                // 小程序环境
                if (systemInfo.uniPlatform.startsWith('mp-')) {
                    return `plugin://face-liveness-detector/static/${relativePath}`;
                }
                // App 环境（Android/iOS）
                if (systemInfo.uniPlatform === 'app' || systemInfo.uniPlatform === 'app-plus') {
                    // App 环境下使用相对路径或 plus:// 协议
                    return `/uni_modules/face-liveness-detector/static/${relativePath}`;
                }
                // H5/Web 环境
                if (systemInfo.uniPlatform === 'h5' || systemInfo.uniPlatform === 'web') {
                    return `/uni_modules/face-liveness-detector/static/${relativePath}`;
                }
            }
        }
        catch (error) {
            // 如果 uni API 不可用，继续使用 fallback
        }
        // Fallback：使用 userAgent 判断（备选方案）
        const userAgent = navigator.userAgent.toLowerCase();
        // 检测是否在小程序环境中
        // 小程序的 userAgent 通常包含特定的标识
        if (/micromessenger|alipay|swan|toutiao|qq|kuaishou/i.test(userAgent)) {
            return `plugin://face-liveness-detector/static/${relativePath}`;
        }
        // 默认使用 H5/Web 路径
        return `/uni_modules/face-liveness-detector/static/${relativePath}`;
    }
    /**
     * 获取模型文件基础路径
     * @returns 模型文件所在目录的路径
     */
    function getModelBasePath() {
        return getResourcePath('models/');
    }
    /**
     * 获取 WASM 文件路径
     * @returns WASM 文件所在目录的路径
     */
    function getWasmPath() {
        return getResourcePath('wasm/');
    }
    /**
     * UniApp 环境检测
     */
    function detectUniAppEnvironment() {
        // 检查是否在 UniApp 环境中（安全的全局对象检查）
        const isUniApp = typeof globalThis !== 'undefined' && globalThis.uni !== undefined;
        let platform = 'unknown';
        let modelPath = '';
        let wasmPath = '';
        if (isUniApp) {
            try {
                // 获取当前运行平台
                const uni = globalThis.uni;
                const systemInfo = uni?.getSystemInfoSync?.();
                platform = systemInfo?.platform || 'unknown';
                // 根据平台设置路径
                if (systemInfo?.uniPlatform) {
                    switch (systemInfo.uniPlatform) {
                        case 'app':
                        case 'app-plus':
                            // 原生 App 环境
                            modelPath = getModelBasePath();
                            wasmPath = getWasmPath();
                            break;
                        case 'h5':
                        case 'web':
                            // H5 环境
                            modelPath = getModelBasePath();
                            wasmPath = getWasmPath();
                            break;
                        case 'mp-wechat':
                        case 'mp-alipay':
                        case 'mp-baidu':
                        case 'mp-toutiao':
                        case 'mp-qq':
                        case 'mp-kuaishou':
                            // 小程序环境 - 需要特殊处理
                            console.warn('[FaceDetectionEngine] Models are not supported in mini-program environment');
                            break;
                        default:
                            modelPath = getModelBasePath();
                            wasmPath = getWasmPath();
                    }
                }
            }
            catch (error) {
                console.warn('[FaceDetectionEngine] Error detecting UniApp environment:', error);
                return {
                    isUniApp: false,
                    platform: 'unknown',
                    modelPath: '',
                    wasmPath: ''
                };
            }
        }
        return {
            isUniApp,
            platform,
            modelPath,
            wasmPath
        };
    }
    /**
     * 在 UniApp 中初始化资源路径
     * 应该在应用启动时调用
     */
    function initializeUniAppResources() {
        const env = detectUniAppEnvironment();
        if (!env.isUniApp) {
            console.warn('[FaceDetectionEngine] Not running in UniApp environment');
            return;
        }
        console.log('[FaceDetectionEngine] Initialized in UniApp environment:', {
            platform: env.platform,
            modelPath: env.modelPath,
            wasmPath: env.wasmPath
        });
        // 如果在小程序环境中，记录警告
        if (env.platform.includes('mp-')) {
            console.error('[FaceDetectionEngine] Face detection is not supported in mini-program environments');
            console.error('[FaceDetectionEngine] Supported platforms: App, H5, Web');
        }
    }
    /**
     * 预加载资源文件（可选，用于提前缓存）
     * 加载所有必需的模型文件和 WASM 后端
     */
    async function preloadResources() {
        const env = detectUniAppEnvironment();
        if (!env.isUniApp || !env.modelPath || !env.wasmPath) {
            console.warn('[FaceDetectionEngine] Cannot preload: UniApp environment not detected or paths not available');
            return false;
        }
        try {
            console.log('[FaceDetectionEngine] Starting resource preload...');
            // 需要预加载的关键资源列表
            const resourcesLoads = [
                // 1. 模型索引文件
                fetch(`${env.modelPath}models.json`).then(r => {
                    if (!r.ok)
                        throw new Error(`Failed to load models.json: ${r.statusText}`);
                    return r.json();
                }),
                // 2. 人脸检测模型（必需）
                fetch(`${env.modelPath}blazeface.json`).then(r => r.json()),
                fetch(`${env.modelPath}blazeface.bin`).then(r => {
                    if (!r.ok)
                        throw new Error(`Failed to load blazeface.bin: ${r.statusText}`);
                    return r.arrayBuffer();
                }),
                // 3. 人脸网格模型（必需）
                fetch(`${env.modelPath}facemesh.json`).then(r => r.json()),
                fetch(`${env.modelPath}facemesh.bin`).then(r => {
                    if (!r.ok)
                        throw new Error(`Failed to load facemesh.bin: ${r.statusText}`);
                    return r.arrayBuffer();
                }),
                // 4. 虹膜检测模型（可选但推荐）
                fetch(`${env.modelPath}iris_landmark.json`).catch(() => null),
                fetch(`${env.modelPath}iris_landmark.bin`).catch(() => null),
                // 5. 活体检测模型（推荐）
                fetch(`${env.modelPath}liveness.json`).then(r => r.json()),
                fetch(`${env.modelPath}liveness.bin`).then(r => {
                    if (!r.ok)
                        throw new Error(`Failed to load liveness.bin: ${r.statusText}`);
                    return r.arrayBuffer();
                }),
                // 6. 防欺骗检测模型（推荐）
                fetch(`${env.modelPath}antispoof.json`).then(r => r.json()),
                fetch(`${env.modelPath}antispoof.bin`).then(r => {
                    if (!r.ok)
                        throw new Error(`Failed to load antispoof.bin: ${r.statusText}`);
                    return r.arrayBuffer();
                }),
                // 7. WASM 后端 JavaScript
                fetch(`${env.wasmPath}tf-backend-wasm.min.js`).then(r => {
                    if (!r.ok)
                        throw new Error(`Failed to load tf-backend-wasm.min.js: ${r.statusText}`);
                    return r.text();
                }),
                // 8. WASM 二进制文件
                fetch(`${env.wasmPath}tfjs-backend-wasm.wasm`).then(r => {
                    if (!r.ok)
                        throw new Error(`Failed to load tfjs-backend-wasm.wasm: ${r.statusText}`);
                    return r.arrayBuffer();
                }),
                // 9. WASM SIMD 版本（性能优化）
                fetch(`${env.wasmPath}tfjs-backend-wasm-simd.wasm`).catch(() => null),
                // 10. WASM 多线程版本（性能优化）
                fetch(`${env.wasmPath}tfjs-backend-wasm-threaded-simd.wasm`).catch(() => null)
            ];
            const results = await Promise.allSettled(resourcesLoads);
            // 统计加载结果
            const successful = results.filter(r => r.status === 'fulfilled' && r.value !== null).length;
            const failed = results.filter(r => r.status === 'rejected' || (r.status === 'fulfilled' && r.value === null)).length;
            console.log(`[FaceDetectionEngine] Resource preload completed: ${successful} succeeded, ${failed} failed/optional`);
            // 记录失败的资源
            if (failed > 0) {
                results.forEach((r, index) => {
                    if (r.status === 'rejected') {
                        console.warn(`[FaceDetectionEngine] Failed to preload resource ${index}:`, r.reason);
                    }
                });
            }
            // 只要关键资源加载成功就返回 true
            // 关键资源：models.json, blazeface, facemesh, liveness, antispoof, wasm
            const criticalLoads = results.slice(0, 12);
            const criticalSuccessful = criticalLoads.filter(r => r.status === 'fulfilled' && r.value !== null).length;
            const allCriticalLoaded = criticalSuccessful >= 10; // 至少加载主要的模型和 WASM
            if (allCriticalLoaded) {
                console.log('[FaceDetectionEngine] All critical resources preloaded successfully');
                return true;
            }
            else {
                console.error('[FaceDetectionEngine] Some critical resources failed to preload');
                return false;
            }
        }
        catch (error) {
            console.error('[FaceDetectionEngine] Error preloading resources:', error);
            return false;
        }
    }

    /**
     * Face Liveness Detection SDK for UniApp
     * Complete SDK wrapper for UniApp integration
     *
     * @example
     * ```javascript
     * import FaceLivenessDetector from '@sssxyd/face-liveness-detector/uniapp'
     *
     * const detector = new FaceLivenessDetector({
     *   min_face_ratio: 0.5,
     *   max_face_ratio: 0.9,
     *   liveness_action_count: 1
     * })
     *
     * detector.on('detector-loaded', () => {
     *   console.log('Detector ready')
     * })
     *
     * await detector.initialize()
     * await detector.startDetection(videoElement)
     * ```
     */
    /**
     * UniApp Face Liveness Detection SDK
     * Wrapper around FaceDetectionEngine optimized for UniApp
     */
    class UniAppFaceDetectionEngine extends FaceDetectionEngine {
        resourcesInitialized = false;
        resourcesPreloaded = false;
        /**
         * Constructor
         * @param config - Configuration object
         */
        constructor(config) {
            // Auto-configure paths for UniApp environment
            const uniAppConfig = detectUniAppEnvironment();
            const finalConfig = {
                ...config,
                human_model_path: config?.human_model_path || getModelBasePath(),
                tensorflow_wasm_path: config?.tensorflow_wasm_path || getWasmPath()
            };
            super(finalConfig);
            // Initialize UniApp resources
            if (uniAppConfig.isUniApp) {
                initializeUniAppResources();
                this.resourcesInitialized = true;
            }
            else {
                console.warn('[FaceLivenessDetectorSDK] Not running in UniApp environment');
            }
        }
        /**
         * Initialize the detection engine with UniApp optimizations
         * Includes automatic resource preloading for better UX
         */
        async initialize() {
            // Optionally preload resources for better UX
            if (!this.resourcesPreloaded) {
                try {
                    await preloadResources();
                    this.resourcesPreloaded = true;
                }
                catch (error) {
                    console.warn('[FaceLivenessDetectorSDK] Resource preloading failed, continuing anyway:', error);
                }
            }
            // Call parent initialize
            return super.initialize();
        }
        /**
         * Start detection in UniApp
         * Automatically handles platform-specific requirements
         */
        async startDetection(videoElement) {
            const uniAppEnv = detectUniAppEnvironment();
            // Check if current platform supports face detection
            if (!uniAppEnv.isUniApp) {
                console.error('[FaceLivenessDetectorSDK] Not in UniApp environment');
                throw new Error('FaceLivenessDetectorSDK requires UniApp environment');
            }
            // Mini-program platforms are not supported
            if (uniAppEnv.platform.includes('mp-')) {
                const errorMsg = `Face detection is not supported in ${uniAppEnv.platform}. Supported platforms: App, H5, Web`;
                console.error('[FaceLivenessDetectorSDK]', errorMsg);
                this.emit('detector-error', {
                    code: 'PLATFORM_NOT_SUPPORTED',
                    message: errorMsg
                });
                throw new Error(errorMsg);
            }
            // Call parent startDetection
            return super.startDetection(videoElement);
        }
        /**
         * Get current resource status
         */
        getResourceStatus() {
            const env = detectUniAppEnvironment();
            return {
                initialized: this.resourcesInitialized,
                preloaded: this.resourcesPreloaded,
                modelPath: getModelBasePath(),
                wasmPath: getWasmPath(),
                environment: env
            };
        }
    }
    /**
     * Create and return a new SDK instance
     *
     * @param config - Configuration object
     * @returns SDK instance ready to use
     *
     * @example
     * ```javascript
     * import { createSDK } from '@sssxyd/face-liveness-detector/uniapp'
     *
     * const detector = createSDK({
     *   liveness_action_count: 2
     * })
     * ```
     */
    function createSDK(config) {
        return new UniAppFaceDetectionEngine(config);
    }
    /**
     * Check if environment supports the SDK
     *
     * @returns Object with support information
     */
    function checkEnvironmentSupport() {
        const env = detectUniAppEnvironment();
        if (!env.isUniApp) {
            return {
                isSupported: false,
                platform: 'unknown',
                reason: 'Not running in UniApp environment'
            };
        }
        if (env.platform.includes('mp-')) {
            return {
                isSupported: false,
                platform: env.platform,
                reason: `Mini-program platforms are not supported. Supported: App, H5, Web`
            };
        }
        return {
            isSupported: true,
            platform: env.platform
        };
    }

    /**
     * Face Detection Engine - Core Detection Engine
     * Framework-agnostic face liveness detection engine
     */

    exports.FaceDetectionEngine = FaceDetectionEngine;
    exports.PhotoAttackDetectionResult = PhotoAttackDetectionResult;
    exports.PhotoAttackDetector = PhotoAttackDetector;
    exports.SimpleEventEmitter = SimpleEventEmitter;
    exports.UniAppFaceDetectionEngine = UniAppFaceDetectionEngine;
    exports.checkEnvironmentSupport = checkEnvironmentSupport;
    exports.createSDK = createSDK;
    exports.default = FaceDetectionEngine;
    exports.detectBrowserEngine = detectBrowserEngine;
    exports.getCvSync = getCvSync;
    exports.getOpenCVVersion = getOpenCVVersion;
    exports.preloadOpenCV = preloadOpenCV;

    Object.defineProperty(exports, '__esModule', { value: true });

}));
//# sourceMappingURL=index.js.map

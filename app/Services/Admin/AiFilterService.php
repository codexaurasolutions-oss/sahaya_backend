<?php

namespace App\Services\Admin;

use OpenAI\Laravel\Facades\OpenAI;

class AiFilterService
{
    public function generateFilters($request, $type = 'staff')
    {
        $question = trim((string) ($request['query'] ?? ''));
        if ($question === '') {
            return [];
        }
        
        try {
            if ($type === 'job') {
                $systemPrompt = 'Convert user request into JSON filters for job search.
                    Available fields:
                    - title (string): Job title/role (e.g., "Driver", "Cook", "Housekeeper")
                    - location (string): Exact city, area, locality, state, or pincode named by the user
                    - city (string): City name
                    - state (string): State name
                    - nearby (boolean): true only when the user asks for jobs near their current location
                    - compensation (object): Salary filters with operators like {"gt": 5000, "lt": 20000}
                    - commitment_type (string): "full-time", "part-time", or "live-in"
                    - compensation_type (string): "monthly", "weekly", "daily", or "hourly"
                    - preferred_hours (string): Working hours
                    - required_skills (string): Skills needed
                    - childcare_experience (boolean)
                    - cooking_required (boolean)
                    - driving_license_required (boolean)
                    - first_aid_certified (boolean)
                    - pet_care_required (boolean)
                    
                    Understand any user language, especially English, Hindi, Telugu, Tamil, Kannada, Malayalam, Marathi, Gujarati, Bengali, Punjabi, Odia, Assamese, Urdu, and Nepali, including romanized/transliterated forms.
                    Return canonical English role/filter values while preserving the requested place name.
                    Normalize obvious spelling variants such as "karachii" to "Karachi" without inventing a different place.
                    IMPORTANT: For job role searches like "driver", "cook", "chef", extract the EXACT role name and put it in "title" field.
                    IMPORTANT: Preserve the requested location separately from role and skill words.
                    IMPORTANT: Set nearby=true ONLY when the user explicitly says near me, nearby, దగ్గర, సమీప, पास, or an equivalent proximity phrase. A generic request for a job is NOT nearby.
                    Example: "driver jobs" -> {"title": "driver"}
                    Example: "cook in Mumbai" -> {"title": "cook", "location": "Mumbai"}
                    Example: "driver jobs near me" -> {"title": "driver", "nearby": true}
                    Telugu example: "నాకు డ్రైవర్ ఉద్యోగం కావాలి" -> {"title": "driver"}
                    Telugu example: "నా దగ్గర డ్రైవర్ ఉద్యోగాలు" -> {"title": "driver", "nearby": true}
                    
                    Return ONLY valid JSON.
                    No markdown.
                    No explanation.';
            } else {
                $systemPrompt = 'Convert user request into JSON filters for staff search.
                    Available fields:
                    - name (string): Staff name
                    - gender (string): "male" or "female"
                    - role (string): Job role/title (e.g. "Driver", "Cook", "Maid", "House Cleaner", "Baby Sitter", "Nanny", "Chef", "Plumber", "Electrician", "Carpenter", "Painter", "Gardener", "Security Guard", "Nurse", "Tutor", "Sweeper", "Laundry", "Dog Walker", "Attendant")
                    - location (string): City, area, or state name (EXTRACT EXACT CITY NAME)
                    - nearby (boolean): true only when the user asks for staff near their current location
                    - salary (object): Salary range with operators like {"gt": 5000, "lt": 20000}
                    - experience (integer): Minimum years of experience (e.g., 2, 5, 10)
                    - languages (array of strings): Languages spoken (e.g., ["hindi", "english", "telugu", "tamil"])
                    - skills (array of strings): Specific skills, tasks, or requirements (e.g., ["driving license", "first aid", "newborn care", "vegetarian cooking", "cleaning"])
                    - general_keywords (array of strings): Any other adjectives or descriptive keywords from the user request (e.g., ["polite", "reliable", "verified", "urgent"])

                    Understand any user language, especially English, Hindi, Telugu, Tamil, Kannada, Malayalam, Marathi, Gujarati, Bengali, Punjabi, Odia, Assamese, Urdu, and Nepali, including romanized/transliterated forms.
                    Return canonical English role/filter values while preserving the requested place name.
                    Normalize obvious spelling variants such as "karachii" to "Karachi" without inventing a different place.
                    IMPORTANT: For role-based searches, ALWAYS extract the job role into the "role" field.
                    IMPORTANT: For city/location searches, extract the city name (e.g., "Vizag", "Mumbai", "Delhi") and put it in the "location" field.
                    IMPORTANT: Do not put cuisine, language, experience, or descriptive words into "location".
                    IMPORTANT: Set nearby=true ONLY when the user explicitly says near me, nearby, దగ్గర, సమీప, पास, or an equivalent proximity phrase. A generic request for staff is NOT nearby.
                    ALWAYS return role in LOWERCASE.
                    Use ONLY these canonical role names (lowercase):
                    driver, cook, chef, maid, house cleaner, nanny, baby sitter, housekeeper, gardener, security, nurse, tutor, plumber, electrician, carpenter, painter, sweeper, laundry, dog walker, attendant, pet caretaker
                    
                    Example: "I need a cook who knows Hindi and has 5 years experience in Vizag" -> {"role": "cook", "location": "Vizag", "languages": ["hindi"], "experience": 5}
                    Example: "Experienced Male Driver with license" -> {"role": "driver", "gender": "male", "skills": ["driving license"]}
                    Example: "female cook in Mumbai who knows south indian food" -> {"role": "cook", "gender": "female", "location": "Mumbai", "skills": ["south indian"]}
                    Example: "polite nanny in delhi" -> {"role": "nanny", "location": "delhi", "general_keywords": ["polite"]}
                    Example: "mere paas driver" -> {"role": "driver", "nearby": true}
                    Telugu example: "నాకు డ్రైవర్ కావాలి" -> {"role": "driver"}
                    Telugu example: "నాకు హైదరాబాద్‌లో డ్రైవర్ కావాలి" -> {"role": "driver", "location": "Hyderabad"}
                    Telugu example: "నా దగ్గర డ్రైవర్ కావాలి" -> {"role": "driver", "nearby": true}
                    Hindi example: "मुझे ड्राइवर चाहिए" -> {"role": "driver"}

                    Return ONLY valid JSON.
                    No markdown.
                    No explanation.';
            }
            
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $question
                    ],
                ],
            ]);

            $content = $response->choices[0]->message->content;
            
            if (strpos($content, '```') !== false) {
                $content = preg_replace('/```(?:json)?\n?|```/', '', $content);
            }
            
            $content = trim($content);
            $decoded = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('AI Filter JSON Decode Error: ' . json_last_error_msg(), ['content' => $content]);
                return [];
            }

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            \Log::warning('AI filter generation failed, using fallback filters', [
                'message' => $e->getMessage(),
                'query' => $question,
                'type' => $type,
            ]);
            return [];
        }
    }
}

#!/bin/bash
# VPL evaluation script with HTML table in evaluation results

# Function to detect and find the main program file (excluding VPL system files)
find_program_file() {
    # Exclude vpl_* files as they are system/test files
    local sql_file=$(ls *.sql 2>/dev/null | grep -v "vpl_" | grep -v "test_cases" | head -n 1)
    if [ -n "$sql_file" ]; then echo "$sql_file"; return 0; fi
    
    local py_file=$(ls *.py 2>/dev/null | grep -v "vpl_" | head -n 1)
    if [ -n "$py_file" ]; then echo "$py_file"; return 0; fi
    
    local java_file=$(ls *.java 2>/dev/null | grep -v "vpl_" | head -n 1)
    if [ -n "$java_file" ]; then echo "$java_file"; return 0; fi
    
    local cpp_file=$(ls *.cpp 2>/dev/null | grep -v "vpl_" | head -n 1)
    if [ -n "$cpp_file" ]; then echo "$cpp_file"; return 0; fi
    
    local c_file=$(ls *.c 2>/dev/null | grep -v "vpl_" | head -n 1)
    if [ -n "$c_file" ]; then echo "$c_file"; return 0; fi
    
    local js_file=$(ls *.js 2>/dev/null | grep -v "vpl_" | head -n 1)
    if [ -n "$js_file" ]; then echo "$js_file"; return 0; fi
    
    return 1
}

# Function to get file extension
get_extension() { echo "${1##*.}"; }

# Function to evaluate SQL queries
evaluate_sql() {
    local STUDENT_SQL="$1"
    
    DB_NAME="test.db"
    TEST_CASES_SQL="vpl_test_cases.sql"
    EXPECTED_OUTPUT="expected.txt"
    STUDENT_OUTPUT="student_output.txt"

    # Clean up
    rm -f "$DB_NAME" "$EXPECTED_OUTPUT" "$STUDENT_OUTPUT"

    # Check if test cases SQL file exists
    if [ ! -f "$TEST_CASES_SQL" ]; then
        # No test cases - just run the SQL and check if it executes successfully
        echo "═══════════════════════════════════════════════════════════════════"
        echo "                    SQL EXECUTION (No Test Cases)"
        echo "═══════════════════════════════════════════════════════════════════"
        echo ""
        echo "Running SQL file: $STUDENT_SQL"
        echo ""
        
        # Run the SQL and capture output
        ERROR_LOG=$(mktemp)
        sqlite3 "$DB_NAME" < "$STUDENT_SQL" > "$STUDENT_OUTPUT" 2>"$ERROR_LOG"
        EXIT_CODE=$?
        
        if [ $EXIT_CODE -ne 0 ]; then
            echo "❌ SQL Execution FAILED"
            echo ""
            echo "Errors:"
            cat "$ERROR_LOG"
            echo ""
            # echo "Grade :=>> 0"
            rm -f "$ERROR_LOG"
            exit 1
        else
            echo "✅ SQL Executed Successfully!"
            echo ""
            if [ -s "$STUDENT_OUTPUT" ]; then
                echo "Output:"
                echo "-------"
                cat "$STUDENT_OUTPUT"
                echo ""
            fi
            # echo "Grade :=>> 100"
            rm -f "$ERROR_LOG"
            exit 0
        fi
    fi

    # Test cases exist - run with comparison
    # Step 1: Create and populate DB
    sqlite3 "$DB_NAME" < "$TEST_CASES_SQL" 2>/dev/null
    if [ $? -ne 0 ]; then
        echo "ERROR: Failed to create database from $TEST_CASES_SQL"
        # echo "Grade :=>> 0"
        exit 1
    fi

    # Step 2: Run student SQL
    echo ".headers off
.mode list
.output $STUDENT_OUTPUT
.read $STUDENT_SQL" | sqlite3 "$DB_NAME" 2>/dev/null
    
    if [ $? -ne 0 ]; then
        echo "ERROR: Failed to execute student SQL file"
        # echo "Grade :=>> 0"
        exit 1
    fi

  

    # Detect if output is multi-line pattern (like diagrams, graphs, ASCII art)
    is_multiline_pattern=false
    if [ ${#expected_lines[@]} -gt 3 ]; then
        # Check if it contains pattern-like characters
        for line in "${expected_lines[@]}"; do
            if [[ "$line" =~ [\*\#\+\-\|\/\\] ]]; then
                is_multiline_pattern=true
                break
            fi
        done
    fi

    echo ""
    echo "Testing SQL query: $STUDENT_SQL"
    echo ""
    
    pass=true
    
    # If it's a multi-line pattern/diagram, use side-by-side comparison
    if [ "$is_multiline_pattern" = true ]; then
        echo "═══════════════════════════════════════════════════════════════════"
        echo "                    PATTERN/DIAGRAM OUTPUT COMPARISON"
        echo "═══════════════════════════════════════════════════════════════════"
        echo ""
        
        # Calculate max width for formatting
        max_width=40
        
        # Print headers
        printf "%-${max_width}s | %-${max_width}s\n" "EXPECTED OUTPUT" "STUDENT OUTPUT"
        printf "%${max_width}s-+-%-${max_width}s\n" | tr ' ' '-'
        
        # Compare line by line with visual alignment
        max_rows=${#expected_lines[@]}
        [ ${#student_lines[@]} -gt $max_rows ] && max_rows=${#student_lines[@]}
        
        for ((i = 0; i < max_rows; i++)); do
            expected="${expected_lines[$i]}"
            student="${student_lines[$i]}"
            
            # Normalize whitespace for comparison (preserve leading/trailing for display)
            e_norm=$(echo "$expected" | tr -d '[:space:]')
            s_norm=$(echo "$student" | tr -d '[:space:]')
            
            # Visual indicator for mismatch
            if [ "$e_norm" != "$s_norm" ]; then
                pass=false
                indicator="❌"
            else
                indicator="✅"
            fi
            
            # Print with truncation if too long
            expected_display=$(printf "%-${max_width}.${max_width}s" "$expected")
            student_display=$(printf "%-${max_width}.${max_width}s" "$student")
            
            printf "%s | %s %s\n" "$expected_display" "$student_display" "$indicator"
        done
        
        echo ""
        printf "%${max_width}s-+-%-${max_width}s\n" | tr ' ' '-'
        
    else
        # Original table format for simple outputs
        # Function to print horizontal line
        print_line() {
            echo "+----------------------+----------------------+----------------------+"
        }

        # Function to print header
        print_header() {
            print_line
            printf "| %-20s | %-20s | %-20s |\n" "Row" "Expected Output" "Student Output"
            print_line
        }

        print_header
        max_rows=${#expected_lines[@]}
        [ ${#student_lines[@]} -gt $max_rows ] && max_rows=${#student_lines[@]}

        for ((i = 0; i < max_rows; i++)); do
            expected="${expected_lines[$i]}"
            student="${student_lines[$i]}"
            
            row_num=$((i+1))
            
            # Normalize whitespace for comparison
            e_norm=$(echo "$expected" | tr -d '[:space:]')
            s_norm=$(echo "$student" | tr -d '[:space:]')

            # Compare
            if [ "$e_norm" != "$s_norm" ]; then
                pass=false
            fi

            printf "| %-20s | %-20s | %-20s |\n" "Row $row_num" "$expected" "$student"
        done

        print_line
    fi
    
    echo ""

    # Step 6: Grade and feedback
    if [ "$pass" = true ]; then
        echo "✅ Output matches expected result."
        # echo "Grade :=>> 100"
    else
        echo "❌ Output does NOT match expected result."
        echo ""
        echo "🔍 DETAILED COMPARISON:"
        echo "───────────────────────────────────────────────────────────────────"
        echo ""
        echo "Expected Output:"
        for line in "${expected_lines[@]}"; do
            echo "  $line"
        done
        echo ""
        echo "Your Output:"
        for line in "${student_lines[@]}"; do
            echo "  $line"
        done
        echo ""
        # echo "Grade :=>> 0"
    fi
    
    # Clean up
    rm -f "$DB_NAME" "$EXPECTED_OUTPUT" "$STUDENT_OUTPUT"
    
    exit 0
}

# Function to compile if needed
compile_program() {
    local file="$1"; local ext=$(get_extension "$file")
    case "$ext" in
        "cpp") g++ -o "${file%.*}" "$file" 2>/dev/null; return $? ;;
        "c") gcc -o "${file%.*}" "$file" 2>/dev/null; return $? ;;
        "java") javac "$file" 2>/dev/null; return $? ;;
        *) return 0 ;;
    esac
}

# Function to execute program with input
execute_program() {
    local file="$1"; local input="$2"; local ext=$(get_extension "$file"); local basename="${file%.*}"
    case "$ext" in
        "py") echo "$input" | python3 "$file" 2>/dev/null ;;
        "cpp"|"c") [ -f "$basename" ] && echo "$input" | ./"$basename" 2>/dev/null || echo "COMPILE_ERROR" ;;
        "java") [ -f "${basename}.class" ] && echo "$input" | java "$basename" 2>/dev/null || echo "COMPILE_ERROR" ;;
        "js") echo "$input" | node "$file" 2>/dev/null ;;
        *) echo "UNSUPPORTED_LANGUAGE" ;;
    esac
}

# Find and compile the submitted file
SUBMITTED_FILE=$(find_program_file)
if [ -z "$SUBMITTED_FILE" ]; then
    echo "ERROR: No supported program file found!"
    echo "Supported files: *.sql, *.py, *.cpp, *.c, *.java, *.js"
    exit 1
fi

# Check if it's a SQL file and handle accordingly
if [[ "$SUBMITTED_FILE" == *.sql ]]; then
    evaluate_sql "$SUBMITTED_FILE"
    exit 0
fi

if ! compile_program "$SUBMITTED_FILE"; then
    echo "ERROR: Compilation failed for $SUBMITTED_FILE"
    exit 1
fi

# Initialize counters
total_tests=0
passed_tests=0

# Check if test cases file exists
if [ ! -f "vpl_evaluate.cases" ]; then
    echo "ERROR: vpl_evaluate.cases file not found!"
    exit 1
fi

# Store test results for HTML generation
test_results=()

echo "Testing with file: $SUBMITTED_FILE"
echo ""

# Arrays to store all test cases
declare -a test_cases
declare -a test_inputs
declare -a test_outputs

# Parse test cases file - read all tests first
current_case=""
current_input=""
current_output=""
in_input_block=false
in_output_block=false

while IFS= read -r line || [[ -n "$line" ]]; do
    # Skip comments
    if [[ "$line" =~ ^#.* ]]; then
        continue
    fi
    
    # Check for case= marker
    if [[ "$line" =~ ^case= ]]; then
        # Save previous test if exists
        if [[ -n "$current_case" ]]; then
            test_cases+=("$current_case")
            test_inputs+=("$current_input")
            test_outputs+=("$current_output")
        fi
        # Start new test
        current_case="${line#case=}"
        current_input=""
        current_output=""
        in_input_block=false
        in_output_block=false
        continue
    fi
    
    # Check for input= marker
    if [[ "$line" =~ ^input= ]]; then
        in_input_block=true
        in_output_block=false
        current_input="${line#input=}"
        # Remove any trailing whitespace from the input= line itself if empty
        if [[ "$current_input" =~ ^[[:space:]]*$ ]]; then
            current_input=""
        fi
        continue
    fi
    
    # Check for output= marker
    if [[ "$line" =~ ^output= ]]; then
        in_input_block=false
        in_output_block=true
        current_output="${line#output=}"
        # Remove any trailing whitespace from the output= line itself
        # This handles cases like "output=  " (spaces after equals)
        if [[ "$current_output" =~ ^[[:space:]]*$ ]]; then
            current_output=""
        fi
        continue
    fi
    
    # If we're in input block, append lines (skip completely empty lines at start/end)
    if [[ "$in_input_block" = true ]]; then
        # Skip blank lines if input is still empty
        if [[ -z "$current_input" ]] && [[ "$line" =~ ^[[:space:]]*$ ]]; then
            continue
        fi
        if [[ -n "$current_input" ]]; then
            current_input+=$'\n'"$line"
        else
            current_input="$line"
        fi
    fi
    
    # If we're in output block, append lines (skip completely empty lines at start/end)
    if [[ "$in_output_block" = true ]]; then
        # Skip blank lines if output is still empty
        if [[ -z "$current_output" ]] && [[ "$line" =~ ^[[:space:]]*$ ]]; then
            continue
        fi
        if [[ -n "$current_output" ]]; then
            current_output+=$'\n'"$line"
        else
            current_output="$line"
        fi
    fi
done < vpl_evaluate.cases

# Don't forget the last test
if [[ -n "$current_case" ]]; then
    test_cases+=("$current_case")
    test_inputs+=("$current_input")
    test_outputs+=("$current_output")
fi

# Clean up inputs and outputs - remove leading/trailing empty lines
for ((i=0; i<${#test_inputs[@]}; i++)); do
    # Trim leading empty lines from input
    while [[ "${test_inputs[$i]}" =~ ^$'\n' ]]; do
        test_inputs[$i]="${test_inputs[$i]#$'\n'}"
    done
    
    # Trim trailing empty lines from input (loop multiple times if needed)
    while [[ "${test_inputs[$i]}" =~ $'\n'$ ]]; do
        test_inputs[$i]="${test_inputs[$i]%$'\n'}"
    done
    
    # Trim leading empty lines from output
    while [[ "${test_outputs[$i]}" =~ ^$'\n' ]]; do
        test_outputs[$i]="${test_outputs[$i]#$'\n'}"
    done
    
    # Trim trailing empty lines from output (loop multiple times if needed)
    while [[ "${test_outputs[$i]}" =~ $'\n'$ ]]; do
        test_outputs[$i]="${test_outputs[$i]%$'\n'}"
    done
done

# Now process all tests
total_tests=${#test_cases[@]}
passed_tests=0

# Always use table format (set to false to use detailed format for multiline I/O)
USE_TABLE_FORMAT=true

# Detect if any test case has multiline input or output
has_multiline_io=false
for ((i=0; i<total_tests; i++)); do
    if [[ "${test_inputs[$i]}" == *$'\n'* ]] || [[ "${test_outputs[$i]}" == *$'\n'* ]]; then
        has_multiline_io=true
        break
    fi
done

# Decide format based on preference
if [ "$USE_TABLE_FORMAT" = true ]; then
    # Always use table format
    echo "┌────────────────────────┬─────────────────┬─────────────────┬─────────────────┬────────────┐"
    printf "│ %-22s │ %-15s │ %-15s │ %-15s │ %-11s│ \n" "Test Case" "Input" "Expected" "Your Output" "Result"
    echo "├────────────────────────┼─────────────────┼─────────────────┼─────────────────┼────────────┤"
elif [ "$has_multiline_io" = true ]; then
    # Use detailed format for multiline I/O (like matrices)
    echo "═══════════════════════════════════════════════════════════════════════════════════"
    echo "                           DETAILED TEST RESULTS"
    echo "═══════════════════════════════════════════════════════════════════════════════════"
else
    # Print table header for simple cases
    echo "┌────────────────────────┬─────────────────┬─────────────────┬─────────────────┬────────────┐"
    printf "│ %-22s │ %-15s │ %-15s │ %-15s │ %-11s│ \n" "Test Case" "Input" "Expected" "Your Output" "Result"
    echo "├────────────────────────┼─────────────────┼─────────────────┼─────────────────┼────────────┤"
fi

for ((test_idx=0; test_idx<total_tests; test_idx++)); do
    current_case="${test_cases[$test_idx]}"
    current_input="${test_inputs[$test_idx]}"
    current_output="${test_outputs[$test_idx]}"
    
    # Run the program with input
    if [ -n "$current_input" ]; then
        actual_output=$(execute_program "$SUBMITTED_FILE" "$current_input")
    else
        actual_output=$(execute_program "$SUBMITTED_FILE" "")
    fi
    
    exit_code=$?
    
    # Determine result
    if [ $exit_code -ne 0 ]; then
        actual_output="EXECUTION_ERROR"
        result="FAIL"
        result_icon="❌"
    else
        # Normalize outputs for comparison
        actual_normalized=$(echo "$actual_output" | tr '\n' ' ' | tr -s ' ' | tr -d ' \t\n\r')
        expected_normalized=$(echo "$current_output" | tr '\n' ' ' | tr -s ' ' | tr -d ' \t\n\r')
        
        # Compare normalized outputs
        if [ "$actual_normalized" = "$expected_normalized" ]; then
            result="PASS"
            result_icon="✅"
            passed_tests=$((passed_tests + 1))
        else
            result="FAIL"
            result_icon="❌"
        fi
    fi
    
    # Store result for later analysis (using § as delimiter since it's unlikely to appear in test data)
    test_results+=("$current_case§${current_input:-"(no input)"}§$current_output§$actual_output§$result")
    
    # Display based on format type
    if [ "$USE_TABLE_FORMAT" = true ]; then
        # Always use table format
        # Create display versions for table
        if [[ "$actual_output" == *$'\n'* ]]; then
            # Multi-line actual output - create preview for table (use | to show line breaks)
            actual_preview=$(echo "$actual_output" | head -c 50 | tr '\n' '|' | tr -s ' ')
            if [ ${#actual_output} -gt 50 ]; then
                actual_display="$actual_preview..."
            else
                actual_display="$actual_preview"
            fi
        else
            # Clean single line output - remove any trailing newlines/carriage returns  
            actual_display=$(echo "$actual_output" | tr -d '\r\n')
        fi
        
        if [[ "$current_output" == *$'\n'* ]]; then
            # Multi-line expected output - create preview for table (use | to show line breaks)
            expected_preview=$(echo "$current_output" | head -c 50 | tr '\n' '|' | tr -s ' ')
            if [ ${#current_output} -gt 50 ]; then
                expected_display="$expected_preview..."
            else
                expected_display="$expected_preview"
            fi
        else
            # Clean single line output - remove any trailing newlines/carriage returns
            expected_display=$(echo "$current_output" | tr -d '\r\n')
        fi
        
        if [[ "$current_input" == *$'\n'* ]]; then
            # Multi-line input - create preview for table (use | to show line breaks)
            input_preview=$(echo "$current_input" | head -c 50 | tr '\n' '|' | tr -s ' ')
            if [ ${#current_input} -gt 50 ]; then
                input_display="$input_preview..."
            else
                input_display="$input_preview"
            fi
        else
            input_display=$(echo "${current_input:-(no input)}" | tr -d '\r\n')
        fi
        
        # Print table row with borders
        case_field=$(printf "%-22.22s" "$(echo "$current_case" | cut -c1-22)")
        input_field=$(printf "%-15.15s" "$(echo "$input_display" | cut -c1-15)")
        expected_field=$(printf "%-15.15s" "$(echo "${expected_display:-$current_output}" | cut -c1-15)")
        actual_field=$(printf "%-15.15s" "$(echo "${actual_display:-$actual_output}" | cut -c1-15)")
        
        # Format result with emoji
        result_text="$result_icon $result"
        result_field=$(printf "%-11s" "$result_text")
        
        printf "│ %s │ %s │ %s │ %s │ %s │\n" \
            "$case_field" "$input_field" "$expected_field" "$actual_field" "$result_field"
        
        # Add row separator (except for the last test)
        if [ $((test_idx + 1)) -lt $total_tests ]; then
            echo "├────────────────────────┼─────────────────┼─────────────────┼─────────────────┼────────────┤"
        fi
        
    elif [ "$has_multiline_io" = true ]; then
        # Detailed format for multiline I/O
        echo ""
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "  Test Case: $current_case"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo ""
        
        # Display Input
        echo "  📥 INPUT:"
        if [ -n "$current_input" ]; then
            echo "$current_input" | sed 's/^/     /'
        else
            echo "     (no input)"
        fi
        echo ""
        
        # Side-by-side comparison for output
        max_width=35
        
        printf "  %-${max_width}s │ %-${max_width}s\n" "EXPECTED OUTPUT" "YOUR OUTPUT"
        printf "  %${max_width}s─┼─%-${max_width}s\n" | tr ' ' '─'
        
        # Split outputs into arrays for line-by-line comparison
        IFS=$'\n' read -rd '' -a expected_arr <<< "$current_output"
        IFS=$'\n' read -rd '' -a actual_arr <<< "$actual_output"
        
        # Get max lines
        max_lines=${#expected_arr[@]}
        [ ${#actual_arr[@]} -gt $max_lines ] && max_lines=${#actual_arr[@]}
        
        # Print line by line with visual indicators
        all_match=true
        for ((j = 0; j < max_lines; j++)); do
            exp_line="${expected_arr[$j]:-}"
            act_line="${actual_arr[$j]:-}"
            
            # Normalize for comparison (remove all whitespace)
            exp_norm=$(echo "$exp_line" | tr -d '[:space:]')
            act_norm=$(echo "$act_line" | tr -d '[:space:]')
            
            # Visual indicator
            if [ "$exp_norm" = "$act_norm" ]; then
                indicator="✓"
            else
                indicator="✗"
                all_match=false
            fi
            
            # Format and print (preserve spacing for visual patterns)
            exp_display=$(printf "%-${max_width}s" "$exp_line")
            act_display=$(printf "%-${max_width}s" "$act_line")
            
            printf "  %s │ %s  %s\n" "$exp_display" "$act_display" "$indicator"
        done
        
        echo ""
        printf "  %${max_width}s─┴─%-${max_width}s\n" | tr ' ' '─'
        echo ""
        
        # Result
        if [ "$result" = "PASS" ]; then
            echo "  ✅ PASS - Output matches perfectly!"
        else
            echo "  ❌ FAIL - Output does not match expected result"
        fi
        echo ""
        
    else
        # Table format for simple I/O
        # Create display versions for table
        if [[ "$actual_output" == *$'\n'* ]]; then
            # Multi-line actual output - create preview for table (clean newlines)
            actual_preview=$(echo "$actual_output" | head -c 50 | tr -d '\r\n' | tr -s ' ')
            if [ ${#actual_output} -gt 50 ]; then
                actual_display="$actual_preview..."
            else
                actual_display="$actual_preview"
            fi
        else
            # Clean single line output - remove any trailing newlines/carriage returns  
            actual_display=$(echo "$actual_output" | tr -d '\r\n')
        fi
        
        if [[ "$current_output" == *$'\n'* ]]; then
            # Multi-line expected output - create preview for table (clean newlines)
            expected_preview=$(echo "$current_output" | head -c 50 | tr -d '\r\n' | tr -s ' ')
            if [ ${#current_output} -gt 50 ]; then
                expected_display="$expected_preview..."
            else
                expected_display="$expected_preview"
            fi
        else
            # Clean single line output - remove any trailing newlines/carriage returns
            expected_display=$(echo "$current_output" | tr -d '\r\n')
        fi
        
        if [[ "$current_input" == *$'\n'* ]]; then
            # Multi-line input - create preview for table
            input_preview=$(echo "$current_input" | head -c 50 | tr -d '\r\n' | tr -s ' ')
            if [ ${#current_input} -gt 50 ]; then
                input_display="$input_preview..."
            else
                input_display="$input_preview"
            fi
        else
            input_display=$(echo "${current_input:-(no input)}" | tr -d '\r\n')
        fi
        
        # Increment pass counter
        if [ "$result" = "PASS" ]; then
            passed_tests=$((passed_tests + 1))
        fi
        
        # Print table row with borders
        case_field=$(printf "%-22.22s" "$(echo "$current_case" | cut -c1-22)")
        input_field=$(printf "%-15.15s" "$(echo "$input_display" | cut -c1-15)")
        expected_field=$(printf "%-15.15s" "$(echo "${expected_display:-$current_output}" | cut -c1-15)")
        actual_field=$(printf "%-15.15s" "$(echo "${actual_display:-$actual_output}" | cut -c1-15)")
        
        # Format result with emoji
        result_text="$result_icon $result"
        result_field=$(printf "%-11s" "$result_text")
        
        printf "│ %s │ %s │ %s │ %s │ %s │\n" \
            "$case_field" "$input_field" "$expected_field" "$actual_field" "$result_field"
        
        # Add row separator (except for the last test)
        if [ $((test_idx + 1)) -lt $total_tests ]; then
            echo "├────────────────────────┼─────────────────┼─────────────────┼─────────────────┼────────────┤"
        fi
    fi
done

# Print table footer only if we used table format
if [ "$USE_TABLE_FORMAT" = true ] || [ "$has_multiline_io" = false ]; then
    echo "└────────────────────────┴─────────────────┴─────────────────┴─────────────────┴────────────┘"
fi

# Print summary
echo ""
echo "Summary: $passed_tests/$total_tests tests passed"

# Calculate percentage
percentage=0
if [ $total_tests -gt 0 ]; then
    percentage=$((passed_tests * 100 / total_tests))
    echo "Success rate: $percentage%"
fi

echo ""

# Show detailed output for failed tests with multiline I/O
if [ "$USE_TABLE_FORMAT" = true ] && [ "$has_multiline_io" = true ]; then
    # Check if there are any failures
    has_failures=false
    for result in "${test_results[@]}"; do
        IFS='§' read -r case input expected actual status <<< "$result"
        if [ "$status" = "FAIL" ]; then
            has_failures=true
            break
        fi
    done
    
    if [ "$has_failures" = true ]; then
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "                    DETAILED VIEW OF FAILED TESTS"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo ""
        
        for result in "${test_results[@]}"; do
            IFS='§' read -r case input expected actual status <<< "$result"
            
            if [ "$status" = "FAIL" ]; then
                echo "❌ Test: $case"
                echo ""
                echo "   📥 Input:"
                echo "$input" | sed 's/^/      /'
                echo ""
                echo "   ✅ Expected Output:"
                echo "$expected" | sed 's/^/      /'
                echo ""
                echo "   ⚠️  Your Output:"
                echo "$actual" | sed 's/^/      /'
                echo ""
                echo "───────────────────────────────────────────────────────────────────"
                echo ""
            fi
        done
    fi
fi

echo ""

# Emit the proposed grade on the execution stream.
#
# The jail captures everything this script prints as the COMPILATION stream, and
# only reports executed=1 -- which is what makes Moodle store execution.txt and
# consult automaticgrading -- when the script leaves an executable vpl_execution
# behind. mod_vpl reads the grade tag from the EXECUTION stream only
# (vpl_submission_CE.class.php::retrieveresult), so the tag has to come from
# vpl_execution, not from here. The human-readable table above is unchanged.
#
# Emitting the tag does not by itself grade anyone: mod_vpl only writes the
# grade on activities with automaticgrading enabled, and merely displays it as
# a proposed grade everywhere else.
: "${VPL_GRADEMIN:=0}"
: "${VPL_GRADEMAX:=10}"

if [ "${total_tests:-0}" -gt 0 ]; then
    proposed_grade=$(awk -v lo="$VPL_GRADEMIN" -v hi="$VPL_GRADEMAX" \
                         -v passed="${passed_tests:-0}" -v total="$total_tests" \
        'BEGIN { printf "%.2f", lo + (hi - lo) * passed / total }')
else
    proposed_grade="$VPL_GRADEMIN"
fi

cat > vpl_execution <<VPL_EXECUTION_END
#!/bin/bash
echo "Summary: ${passed_tests:-0}/${total_tests:-0} tests passed"
echo "Grade :=>> $proposed_grade"
VPL_EXECUTION_END
chmod +x vpl_execution
